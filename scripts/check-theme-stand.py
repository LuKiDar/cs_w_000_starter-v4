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


def php_files(theme: Path):
    for p in theme.rglob("*.php"):
        if any(part in SKIP_DIRS for part in p.parts):
            continue
        yield p


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="replace")


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
        defined |= set(re.findall(r"function\s+(cs__\w+)", src))
        defined |= set(re.findall(r"class\s+(cs__\w+|CS_\w+)", src))

    missing = []
    for f in php_files(theme):
        src = read(f)
        for name in set(re.findall(r"\bnew\s+(cs__\w+|CS_\w+)\s*\(", src)):
            if name not in defined:
                missing.append((f, f"new {name}()"))
        for name in set(re.findall(r"\b(cs__\w+)\s*\(", src)):
            if name.startswith("cs__") and name not in defined:
                # function_exists guards are an accepted declaration of an optional dependency
                if f"function_exists('{name}')" in src or f'function_exists("{name}")' in src:
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
    r = subprocess.run(
        ["git", "-C", str(theme), "ls-files"], capture_output=True, text=True
    )
    if r.returncode != 0:
        return []
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

    failed = 0
    for name, fn in CHECKS:
        try:
            problems = fn(theme)
        except Exception as e:  # a crashing check is a failing check
            problems = [(theme, f"check raised {type(e).__name__}: {e}")]
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