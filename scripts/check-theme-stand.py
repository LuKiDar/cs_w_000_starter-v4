#!/usr/bin/env python3
"""Theme stand check: is this theme deliverable?

Usage: python scripts/check-theme-stand.py [theme-dir]
Exit code 0 = all checks passed, 1 = at least one failed.
"""

import json
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path


def find_php() -> str:
    """Locate the PHP CLI, in order: $CSWP_PHP, PATH, then this dev machine's Local install.

    This script ships inside the theme and the client's team is expected to run it,
    so a hard-coded path to one developer's machine must not be the only way to
    find PHP. The Local path stays as the last fallback so the owner's own
    environment keeps working without any setup.
    """
    candidates = [
        os.environ.get("CSWP_PHP"),
        shutil.which("php"),
        r"C:/Users/Admin/AppData/Roaming/Local/lightning-services/php-8.1.23+0/bin/win64/php.exe",
    ]
    for candidate in candidates:
        if candidate and Path(candidate).exists():
            return candidate
    return "php"  # nothing found: let the syntax check fail loudly rather than silently


PHP = find_php()

SKIP_DIRS = {"node_modules", ".git", "vendor", "build"}

# A check returns this when it could not run at all -- no git on the machine, or the
# directory is not a working tree. It is reported as SKIP and does not fail the gate:
# claiming a pass would be a lie, and failing would be a false alarm on a legitimate
# use such as a delivered copy that is not a git checkout.
SKIP = object()


def php_files(theme: Path):
    for p in theme.rglob("*.php"):
        if any(part in SKIP_DIRS for part in p.parts):
            continue
        yield p


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="replace")


GUARD_WINDOW = 4  # how many lines above a call a function_exists() guard may sit


def _indent_of(line: str) -> int:
    return len(line) - len(line.lstrip())


PHP_TAG_RE = re.compile(r"<\?(?:php|=)?|\?>")


def _closes_block(line: str, guard_indent: int) -> bool:
    """True when `line` closes a block opened at `guard_indent`.

    PHP tags are stripped first, because a template closer is routinely written
    `<?php } ?>` or `<?php endif; ?>` -- testing the raw line for a leading `}`
    misses the first of those entirely and reopens the hole the check exists to close.
    """
    if _indent_of(line) > guard_indent:
        return False
    body = PHP_TAG_RE.sub(" ", line).strip()
    if body.startswith("}"):
        return True
    # `endif` must begin the statement. A mention inside a comment, a string literal
    # or a variable name (`$endif_check`) closes nothing, and counting it as a closer
    # would report a call that is genuinely guarded.
    return body.startswith("endif")


def _guarded(lines, index: int, name: str) -> bool:
    """True when a function_exists() guard for `name` actually encloses this call.

    The guard has to *enclose* the call, not merely appear somewhere in the file.
    Testing the whole file text let one guarded call exempt every unguarded call to
    the same symbol in that file -- which is exactly the shape of the v3 fatal this
    check exists to catch, so the exemption could be defeated by adding the guard it
    was written to accept.

    Four conditions must all hold:
      1. a guard for this exact `name` is on the call's own line, or within
         GUARD_WINDOW lines above it;
      2. the call is indented deeper than the guard line;
      3. the guard does not close its own block on the guard line (the one-line
         `if ( function_exists('x') ) { x(); }` form); and
      4. no closer at or above the guard's own indent sits between guard and call.

    Conditions 3 and 4 are what separate a call inside the guard's block from one that
    merely follows a guard whose block has already ended. Proximity and indentation
    alone exempt the second case, because it is deeper-indented and still in the window.
    """
    needles = (f"function_exists('{name}')", f'function_exists("{name}")')
    if any(n in lines[index] for n in needles):
        return True

    guard_line = None
    for j in range(index - 1, max(-1, index - GUARD_WINDOW - 1), -1):
        if any(n in lines[j] for n in needles):
            guard_line = j
            break
    if guard_line is None:
        return False

    guard_indent = _indent_of(lines[guard_line])
    if _indent_of(lines[index]) <= guard_indent:
        return False

    # Condition 3: the guard line itself may open and close the block, in which case
    # nothing below it is inside the guard. Only a closer *after* the guard expression
    # counts -- the `{` that opens the block must not be read as one.
    guard_text = lines[guard_line]
    after_needle = guard_text[max(guard_text.find(n) for n in needles) :]
    if "}" in after_needle:
        return False

    # Condition 4: the block must still be open at the call.
    return not any(
        _closes_block(line, guard_indent) for line in lines[guard_line + 1 : index]
    )


# --- checks -----------------------------------------------------------------

def check_php_syntax(theme: Path):
    bad = []
    for f in php_files(theme):
        r = subprocess.run([PHP, "-l", str(f)], capture_output=True, text=True)
        if r.returncode != 0:
            bad.append((f, r.stdout.strip() or r.stderr.strip()))
    return bad


def check_block_json(theme: Path):
    problems = []
    for bj in sorted(theme.glob("parts/block/*/block.json")):
        try:
            data = json.loads(read(bj))
        except json.JSONDecodeError as e:
            problems.append((bj, f"invalid JSON: {e}"))
            continue
        for key in ("name", "title", "category", "apiVersion"):
            if key not in data:
                problems.append((bj, f"missing {key!r}"))
        if data.get("apiVersion") != 3:
            problems.append((bj, f"apiVersion is {data.get('apiVersion')!r}, expected 3"))
        for key in ("style", "script", "editorStyle"):
            ref = data.get(key)
            if isinstance(ref, str) and ref.startswith("file:"):
                target = bj.parent / ref[len("file:"):].lstrip("./")
                if not target.exists():
                    problems.append((bj, f"{key} points at missing {target.name}"))
    return problems


def check_symbols(theme: Path):
    """Every cs__ symbol used by a template must be defined in the theme.

    This is the check that would have caught the v3 fatal:
    Class "cs__primary_menu_walker" not found.
    """
    defined = set()
    for f in php_files(theme):
        src = read(f)
        defined |= set(re.findall(r"function\s+&?\s*(cs__\w+)", src))
        defined |= set(re.findall(r"class\s+(cs__\w+|CS_\w+)", src))

    missing = []
    for f in php_files(theme):
        lines = read(f).splitlines()
        for i, line in enumerate(lines):
            # Report per line so two call sites stay two findings, and so a `new`
            # expression is not also counted again by the plain-call scan below.
            new_names = set(re.findall(r"\bnew\s+(cs__\w+|CS_\w+)\s*\(", line))
            for name in new_names:
                if name not in defined:
                    missing.append((f, f"new {name}()"))
            for name in set(re.findall(r"\b(cs__\w+)\s*\(", line)):
                if name in defined or name in new_names:
                    continue
                # function_exists guards are an accepted declaration of an optional
                # dependency -- but only for the call they actually guard.
                if _guarded(lines, i, name):
                    continue
                missing.append((f, f"{name}()"))
    return missing


def check_escaping(theme: Path):
    problems = []
    pattern = re.compile(r"<\?=\s*\$[A-Za-z_]")
    for f in php_files(theme):
        for i, line in enumerate(read(f).splitlines(), 1):
            if pattern.search(line) and "esc_" not in line and "wp_kses" not in line:
                problems.append((f, f"line {i}: unescaped output"))
    return problems


def check_build_artifacts(theme: Path):
    try:
        r = subprocess.run(
            ["git", "-C", str(theme), "ls-files"], capture_output=True, text=True
        )
    except FileNotFoundError:
        return SKIP  # no git on this machine
    if r.returncode != 0:
        return SKIP  # not a git working tree
    bad = [
        line for line in r.stdout.splitlines()
        if line.endswith((".min.css", ".min.js", ".map"))
    ]
    return [(theme, f"tracked build artifact: {p}") for p in bad]


CHECKS = [
    ("PHP syntax", check_php_syntax),
    ("block.json validity", check_block_json),
    ("symbol resolution", check_symbols),
    ("output escaping", check_escaping),
    ("build artifacts not tracked", check_build_artifacts),
]


def main():
    theme = Path(sys.argv[1] if len(sys.argv) > 1 else os.getcwd()).resolve()
    print(f"Stand check: {theme}\n")

    # Without this guard a typo, a wrong cwd or a failed checkout iterates an empty
    # tree, every check finds nothing wrong, and the gate reports success over nothing.
    if not theme.is_dir():
        print(f"FAIL  theme directory not found: {theme}")
        return 1

    failed = 0
    for name, fn in CHECKS:
        try:
            problems = fn(theme)
        except Exception as e:  # a crashing check is a failing check
            problems = [(theme, f"check raised {type(e).__name__}: {e}")]
        if problems is SKIP:
            print(f"SKIP  {name}  (could not run here)")
            continue
        if problems:
            failed += 1
            print(f"FAIL  {name}  ({len(problems)})")
            for where, what in problems[:20]:
                print(f"        {where.name}: {what}")
            if len(problems) > 20:
                print(f"        … and {len(problems) - 20} more")
        else:
            print(f"PASS  {name}")

    print()
    if failed:
        print(f"{failed} check(s) failed.")
        return 1
    print("All checks passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())