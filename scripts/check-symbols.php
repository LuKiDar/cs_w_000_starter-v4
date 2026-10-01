<?php
/**
 * Report `cs__` symbols that are called but defined nowhere in the theme.
 *
 * Usage:  php scripts/check-symbols.php <theme-dir>
 * Output: one JSON object per line:
 *           {"file":"<path relative to theme>","line":<int>,"symbol":"<name>","kind":"call"|"new"}
 * Exit:   0 when the analysis ran, 2 when it could not (bad argument). The caller
 *          decides what the output means; a non-zero exit would be indistinguishable
 *          from the caller's own failure.
 *
 * Why a tokenizer and not a regex: PHP's block structure is neither indentation-based
 * nor line-based, so whether a call sits inside a `function_exists()` guard cannot be
 * decided by scanning text. Three rounds of regex heuristics each missed new spellings
 * -- an over-indented closer, a tab/space mix, `<?php } ?>`, a closer behind a comment,
 * a one-line guard -- and introduced false alarms of their own. Tokens do not guess.
 */

declare(strict_types=1);

const SKIP_DIRS = ['node_modules', '.git', 'vendor', 'build'];

/**
 * Collect the theme's PHP files as [absolute path, path relative to the theme].
 */
function theme_files(string $root): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if (!$f->isFile() || $f->getExtension() !== 'php') {
            continue;
        }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        foreach (SKIP_DIRS as $skip) {
            if (str_starts_with($rel, $skip . '/') || str_contains($rel, '/' . $skip . '/')) {
                continue 2;
            }
        }
        $files[] = [$f->getPathname(), $rel];
    }
    sort($files);
    return $files;
}

/**
 * The next significant token at or after $i, skipping whitespace and comments.
 */
function next_significant(array $tokens, int $i): array|string|null
{
    $n = count($tokens);
    for ($j = $i + 1; $j < $n; $j++) {
        $t = $tokens[$j];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $t;
    }
    return null;
}

function is_skippable(mixed $t): bool
{
    return is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

/**
 * One file: what it defines, and which of its `cs__` uses resolve nowhere.
 *
 * @return array{defined: list<string>, reported: list<array{int, string, string}>}
 */
function analyze(string $src): array
{
    $tokens = token_get_all($src);
    $n = count($tokens);

    $defined = [];
    $reported = [];

    $depth = 0;
    $openGuards = [];       // stack of ['name' => string, 'depth' => int, 'alt' => bool]
    $pendingGuard = null;   // a function_exists('cs__x') seen, awaiting its block opener
    $pendingTernary = false;
    $prevSig = null;        // last significant token, for call context

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        // --- single-character tokens -----------------------------------------
        if (!is_array($t)) {
            switch ($t) {
                case '{':
                    $depth++;
                    if ($pendingGuard !== null) {
                        $openGuards[] = ['name' => $pendingGuard, 'depth' => $depth, 'alt' => false];
                        $pendingGuard = null;
                    }
                    break;

                case '}':
                    while ($openGuards && !end($openGuards)['alt'] && end($openGuards)['depth'] === $depth) {
                        array_pop($openGuards);
                    }
                    $depth--;
                    break;

                case ':':
                    // Alternative syntax (`if ( ... ) : ... endif;`). A ternary never
                    // opens a block, and `?` before the `:` is what tells them apart.
                    if ($pendingGuard !== null && !$pendingTernary) {
                        $openGuards[] = ['name' => $pendingGuard, 'depth' => $depth + 1, 'alt' => true];
                    }
                    $pendingGuard = null;
                    break;

                case '?':
                    $pendingTernary = true;
                    break;

                case ';':
                    $pendingGuard = null;
                    $pendingTernary = false;
                    break;
            }
            $prevSig = $t;
            continue;
        }

        // --- tokens we ignore -------------------------------------------------
        if (is_skippable($t)) {
            continue;
        }

        switch ($t[0]) {
            case T_ENDIF:
                if ($openGuards && end($openGuards)['alt']) {
                    array_pop($openGuards);
                }
                break;

            case T_FUNCTION:
                $nx = next_significant($tokens, $i);
                if ($nx === '&') {
                    $nx = next_significant($tokens, $i + 1);
                }
                if (is_array($nx) && $nx[0] === T_STRING
                    && (str_starts_with($nx[1], 'cs__') || str_starts_with($nx[1], 'CS_'))) {
                    $defined[$nx[1]] = true;
                }
                break;

            case T_CLASS:
            case T_INTERFACE:
            case T_TRAIT:
                $nx = next_significant($tokens, $i);
                if (is_array($nx) && $nx[0] === T_STRING
                    && (str_starts_with($nx[1], 'cs__') || str_starts_with($nx[1], 'CS_'))) {
                    $defined[$nx[1]] = true;
                }
                break;

            case T_NEW:
                $nx = next_significant($tokens, $i);
                if (is_array($nx) && $nx[0] === T_STRING
                    && (str_starts_with($nx[1], 'cs__') || str_starts_with($nx[1], 'CS_'))) {
                    $reported[] = [$nx[2], $nx[1], 'new'];
                }
                break;

            case T_STRING:
                $name = $t[1];

                if ($name === 'function_exists') {
                    $j = $i + 1;
                    while ($j < $n && is_skippable($tokens[$j])) {
                        $j++;
                    }
                    if ($j < $n && $tokens[$j] === '(') {
                        $k = $j + 1;
                        while ($k < $n && is_skippable($tokens[$k])) {
                            $k++;
                        }
                        if ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_CONSTANT_ENCAPSED_STRING) {
                            $inner = trim($tokens[$k][1], "'\"");
                            if (str_starts_with($inner, 'cs__') || str_starts_with($inner, 'CS_')) {
                                $pendingGuard = $inner;
                                $pendingTernary = false;
                            }
                        }
                    }
                    break;
                }

                if (!str_starts_with($name, 'cs__')) {
                    break;
                }
                // A call, not a definition or a method: `cs__x(` with no `function`,
                // `new`, `->` or `::` in front of it.
                if (next_significant($tokens, $i) !== '(') {
                    break;
                }
                if (is_array($prevSig)
                    && in_array($prevSig[0], [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NS_SEPARATOR], true)) {
                    break;
                }
                $guarded = false;
                foreach ($openGuards as $g) {
                    if ($g['name'] === $name) {
                        $guarded = true;   // inside a guard for this very symbol
                        break;
                    }
                }
                if (!$guarded) {
                    $reported[] = [$t[2], $name, 'call'];
                }
                break;
        }

        $prevSig = $t;
    }

    return ['defined' => array_keys($defined), 'reported' => $reported];
}

// --- driver ------------------------------------------------------------------

$root = rtrim($argv[1] ?? '.', "/\\");
if (!is_dir($root)) {
    fwrite(STDERR, "not a directory: $root\n");
    exit(2);
}

$files = theme_files($root);

$defined = [];
$perFile = [];
foreach ($files as [$abs, $rel]) {
    $result = analyze((string) file_get_contents($abs));
    $perFile[$rel] = $result['reported'];
    foreach ($result['defined'] as $name) {
        $defined[$name] = true;
    }
}

foreach ($perFile as $rel => $reported) {
    foreach ($reported as [$line, $symbol, $kind]) {
        if (isset($defined[$symbol])) {
            continue;
        }
        echo json_encode(
            ['file' => $rel, 'line' => $line, 'symbol' => $symbol, 'kind' => $kind],
            JSON_UNESCAPED_SLASHES
        ), "\n";
    }
}