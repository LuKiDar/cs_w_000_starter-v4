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
        # An underscore-prefixed folder is a template or a scratch block: `_skeleton`
        # carries {{FUNC}} placeholders and is not parseable PHP. `cs__get_blocks()`
        # excludes the same folders from registration.
        if any(part.startswith("_") for part in p.relative_to(theme).parts):
            continue
        yield p


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="replace")


def _display(where: Path, theme: Path) -> str:
    """Show a finding's file relative to the theme when it lives there.

    The basename alone is ambiguous: a theme with two `render.php` files reports two
    findings that read identically, and whoever triages them cannot tell which file
    to open. A finding about the theme itself prints the theme's name rather than `.`.
    """
    try:
        rel = where.relative_to(theme)
    except ValueError:
        return str(where)
    return theme.name if str(rel) == "." else str(rel)


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
        # `_skeleton` is the template every block is generated from, not a block: it
        # holds {{SLUG}} placeholders and has no compiled assets to point at.
        if bj.parent.name.startswith("_"):
            continue
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

    The analysis is delegated to `scripts/check-symbols.php`, which tokenizes rather
    than pattern-matches. PHP's block structure is neither indentation-based nor
    line-based, so whether a call sits inside a `function_exists()` guard cannot be
    decided by scanning text: three rounds of regex heuristics each missed new
    spellings (an over-indented closer, a tab/space mix, `<?php } ?>`, a closer behind
    a comment, a one-line guard) and introduced false alarms of their own. Tokens do
    not guess. See that file for the reasoning.

    This is the check that would have caught the v3 fatal:
    Class "cs__primary_menu_walker" not found.
    """
    helper = Path(__file__).resolve().parent / "check-symbols.php"
    if not helper.exists():
        return [(theme, f"symbol analysis helper is missing: {helper}")]
    try:
        r = subprocess.run(
            [PHP, str(helper), str(theme)], capture_output=True, text=True
        )
    except FileNotFoundError:
        return SKIP  # no PHP on this machine; the syntax check reports the same
    if r.returncode != 0:
        return [(theme, f"symbol analysis failed: {r.stderr.strip() or r.returncode}")]
    problems = []
    for line in r.stdout.splitlines():
        if not line.strip():
            continue
        try:
            found = json.loads(line)
        except json.JSONDecodeError:
            problems.append((theme, f"symbol analysis emitted junk: {line.strip()}"))
            continue
        symbol = found.get("symbol")
        shown = f"new {symbol}()" if found.get("kind") == "new" else f"{symbol}()"
        problems.append((theme / found["file"], f"line {found['line']}: {shown}"))
    return problems


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
                print(f"        {_display(where, theme)}: {what}")
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