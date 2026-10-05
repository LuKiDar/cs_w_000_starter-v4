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
    """Locate the PHP CLI, in order: $CSWP_PHP, PATH, then this machine's newest Local install.

    This script ships inside the theme and the client's team is expected to run it,
    so a hard-coded path to one developer's machine must not be the only way to
    find PHP. The Local glob stays as the last fallback so the owner's own
    environment keeps working without any setup.

    The version is globbed, never pinned. A pinned 8.1.23 linted this theme's files
    while the site itself ran 8.4.10, so the check was reporting on a PHP that was
    not in use -- and it would have kept doing so silently.
    """
    candidates = [os.environ.get("CSWP_PHP"), shutil.which("php")]

    # Local stores its services under %APPDATA%\Local (Roaming), the same base the
    # cs-wp harness uses -- not %LOCALAPPDATA%, which points at AppData\Local and
    # holds no such directory. Both roots are checked, and whichever exists is
    # globbed for installs, because discovery must not depend on one env var being
    # the "right" one.
    local_roots = [
        Path(base) / "Local" / "lightning-services"
        for base in (os.environ.get("APPDATA"), os.environ.get("LOCALAPPDATA"))
        if base
    ]
    installs = []
    for local in local_roots:
        if local.is_dir():
            installs.extend(local.glob("php-*/bin/win64/php.exe"))
    installs.sort(
        key=lambda p: [int(n) for n in re.findall(r"\d+", p.parts[-4].split("+")[0])],
        reverse=True,
    )
    candidates.extend(str(p) for p in installs)

    for candidate in candidates:
        if candidate and Path(candidate).exists():
            return candidate
    return "php"


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


def _in_generated_dir(theme: Path, p: Path) -> bool:
    """True when a path sits under an underscore-prefixed directory.

    Underscore-prefixed *files* are real inputs (`_base.scss`, `_tokens.scss` are
    `@use`-d); only underscore-prefixed *folders* (`_skeleton`) are templates whose
    placeholders the compiler never sees.
    """
    return any(part.startswith("_") for part in p.relative_to(theme).parts[:-1])


def _output_problems(out: Path, newest: float, source_note: str):
    """Missing / empty / stale findings for one emitted file.

    `source_note` names the source the freshness comparison uses, so the message
    tells the reader which clock an output is behind. Both build steps fail the
    same silent way -- an error or a skipped build leaves the previous output on
    disk -- so CSS and JS share this check.
    """
    if not out.exists():
        return [(out, "expected build output is missing")]
    if out.stat().st_size == 0:
        return [(out, "build output is empty")]
    if out.stat().st_mtime < newest:
        return [(out, f"stale build output (older than {source_note})")]
    return []


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
    problems = [(theme, f"tracked build artifact: {p}") for p in bad]

    # Emitted assets must exist, be non-empty, and be newer than their sources.
    # This guards an OBSERVED failure, not an imagined one: on 2026-10-05 a partial
    # with a Sass syntax error, `@use`-d from main.scss, left assets/css/main.min.css
    # untouched -- mtime unchanged -- while `npm run build` printed the error and
    # still exited 0. A broken compile therefore shipped the previous stylesheet
    # silently, and every "build passed" that day was weak evidence. Comparing each
    # output to the newest source .scss catches that stale stylesheet directly.
    outputs = []
    for entry in sorted(theme.glob("assets/scss/*.scss")):
        if not entry.name.startswith("_"):  # top-level entries only; _*.scss are partials
            outputs.append(theme / "assets" / "css" / f"{entry.stem}.min.css")
    for block in sorted(theme.glob("parts/block/*")):
        if not block.is_dir() or block.name.startswith("_"):
            continue
        for name in ("style.scss", "editor.scss"):
            if (block / name).exists():
                outputs.append(block / f"{Path(name).stem}.min.css")

    sources = [
        p for p in theme.glob("assets/scss/**/*.scss")
        if not _in_generated_dir(theme, p)
    ]
    sources += [
        p for p in theme.glob("parts/block/**/*.scss")
        if not _in_generated_dir(theme, p)
    ]
    newest = max((p.stat().st_mtime for p in sources), default=0.0)

    for out in outputs:
        problems.extend(_output_problems(out, newest, "the newest source .scss"))

    # The same guard now covers the JS outputs. The esbuild task fails identically:
    # a syntax error or a skipped build leaves the previous main.min.js /
    # script.min.js on disk while the site serves old JS, so staleness there is the
    # same silent class this check exists for. The global entry is compared to the
    # newest global source; each block's output to its own script.js, so a block
    # edit cannot be masked by another block's build.
    js_sources = [
        p for p in theme.glob("assets/js/src/**/*.js")
        if not _in_generated_dir(theme, p)
    ]
    js_newest = max((p.stat().st_mtime for p in js_sources), default=0.0)
    for out in sorted(theme.glob("assets/js/dist/*.min.js")):
        problems.extend(_output_problems(out, js_newest, "the newest source .js"))
    for block in sorted(theme.glob("parts/block/*")):
        if not block.is_dir() or block.name.startswith("_"):
            continue
        source = block / "script.js"
        if source.exists():
            problems.extend(
                _output_problems(
                    block / "script.min.js",
                    source.stat().st_mtime,
                    "its source script.js",
                )
            )
    return problems


CHECKS = [
    ("PHP syntax", check_php_syntax),
    ("block.json validity", check_block_json),
    ("symbol resolution", check_symbols),
    ("output escaping", check_escaping),
    ("build artifacts not tracked / CSS + JS fresh", check_build_artifacts),
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