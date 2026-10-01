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
 * decided by scanning text. Four rounds of regex heuristics each missed new spellings
 * -- an over-indented closer, a tab/space mix, `<?php } ?>`, a closer behind a comment,
 * a one-line guard -- and introduced false alarms of their own. Tokens do not guess.
 *
 * What counts as a guard. A `function_exists()` test protects a call only when the
 * test actually guarantees the symbol exists at that point:
 *   - every `function_exists('cs__x')` in the condition contributes, not just the last,
 *     because `&&`-joined tests all hold when the body runs;
 *   - a `||` anywhere in the condition destroys that guarantee for every operand, so a
 *     `||`-joined test protects nothing and is not treated as a guard;
 *   - the guarded body may be a `{ ... }` block, a template `: ... endif;` region, a
 *     brace-less single statement, or a `? :` consequent;
 *   - a test used as a plain value (`$x = function_exists('cs__x');`) guards nothing.
 * Both directions matter: a missed closer lets a fatal through, and a false alarm
 * trains a team to ignore the gate.
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

function is_cs_symbol(string $name): bool
{
    return str_starts_with($name, 'cs__') || str_starts_with($name, 'CS_');
}

/**
 * Is $name protected by one of the guard scopes currently open?
 *
 * @param list<array{names: list<string>, depth: int, kind: string}> $open
 */
function guarded(array $open, string $name): bool
{
    foreach ($open as $g) {
        if (in_array($name, $g['names'], true)) {
            return true;
        }
    }
    return false;
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

    $depth = 0;          // brace depth
    $paren = 0;          // paren depth
    $open = [];          // guard scopes: names + the depth they cover + their kind
    $pending = [];       // function_exists('cs__x') names seen in the current condition
    $pendingParen = -1;  // paren depth at the outermost such test
    $sawOr = false;      // the condition is `||`-joined, so it guarantees nothing
    $condOr = false;     // a `||` seen inside the enclosing parens, guard or not
    $prevSig = null;     // last significant token, for call context

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (is_skippable($t)) {
            continue;
        }

        // --- 1. paren bookkeeping, before anything that depends on it ---------
        if ($t === '(') {
            $paren++;
        } elseif ($t === ')') {
            $paren--;
        }

        // --- 2. a `||` in the condition destroys the guarantee ----------------
        // It counts wherever it sits: `$b || function_exists('cs__x')` guarantees
        // no more about cs__x than the other order does, and the old code missed
        // exactly that ordering.
        if (is_array($t) && ($t[0] === T_BOOLEAN_OR || $t[0] === T_LOGICAL_OR)) {
            if ($paren > 0) {
                $condOr = true;
            }
            if ($pending) {
                $sawOr = true;
            }
        }

        // --- 3. the condition has closed: this token opens, or is, its body ---
        // `$paren < $pendingParen` when the test sits inside an enclosing group
        // (`if ( ... )`); `$pendingParen === 0` means the test is the whole expression
        // (`function_exists('cs__x') ? ... : ...`), which closes on its own `)`.
        $closed = $paren < $pendingParen || ($pendingParen === 0 && $paren === 0);
        if ($pending && $closed && $t !== ')') {
            $kind = 'stmt';
            if ($t === '{') {
                $kind = 'block';
                $depth++;
            } elseif ($t === ':') {
                $kind = 'alt';
            } elseif ($t === ';' || $t === ',') {
                $kind = null;       // the test was used as a value, not as a guard
            }
            if ($kind !== null && !$sawOr) {
                $open[] = [
                    'names' => $pending,
                    'depth' => $kind === 'alt' ? $depth + 1 : $depth,
                    'kind'  => $kind,
                ];
            }
            $pending = [];
            $pendingParen = -1;
            $sawOr = false;
            $condOr = false;
            if ($kind === 'block' || $kind === 'alt') {
                $prevSig = $t;
                continue;           // `{` is already counted; `:` opens nothing else
            }
            // a brace-less body or a `?` consequent: fall through and treat this
            // token normally, so a call on it is seen with the guard already open
        }

        // --- 4. ordinary token handling ---------------------------------------
        if ($t === '{') {
            $depth++;
            $condOr = false;
        } elseif ($t === '}') {
            while ($open && end($open)['kind'] !== 'alt' && end($open)['depth'] === $depth) {
                array_pop($open);
            }
            $depth--;
            $condOr = false;
        } elseif ($t === ';') {
            while ($open && end($open)['kind'] === 'stmt' && end($open)['depth'] === $depth) {
                array_pop($open);
            }
            $pending = [];
            $sawOr = false;
            $condOr = false;
        } elseif ($t === ':') {
            while ($open && end($open)['kind'] === 'stmt') {
                array_pop($open);   // the `?` consequent ends here
            }
        } elseif (is_array($t) && $t[0] === T_ENDIF) {
            if ($open && end($open)['kind'] === 'alt') {
                array_pop($open);
            }
        } elseif (is_array($t) && ($t[0] === T_FUNCTION || $t[0] === T_CLASS
            || $t[0] === T_INTERFACE || $t[0] === T_TRAIT)) {
            $nx = next_significant($tokens, $i);
            if ($nx === '&') {
                $nx = next_significant($tokens, $i + 1);
            }
            if (is_array($nx) && $nx[0] === T_STRING && is_cs_symbol($nx[1])) {
                $defined[$nx[1]] = true;
            }
        } elseif (is_array($t) && $t[0] === T_NEW) {
            $nx = next_significant($tokens, $i);
            if (is_array($nx) && $nx[0] === T_STRING && is_cs_symbol($nx[1])) {
                $reported[] = [$nx[2], $nx[1], 'new'];
            }
        } elseif (is_array($t) && ($t[0] === T_STRING || $t[0] === T_NAME_FULLY_QUALIFIED)) {
            // PHP 8 lexes `\cs__x` as a single T_NAME_FULLY_QUALIFIED token rather
            // than T_NS_SEPARATOR + T_STRING, so a leading separator has to be
            // stripped here or a fully qualified global call goes unseen.
            $name = ltrim($t[1], '\\');

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
                        if (is_cs_symbol($inner)) {
                            if (!$pending) {
                                $pendingParen = $paren;
                                $sawOr = $condOr;
                            }
                            $pending[] = $inner;
                        }
                    }
                }
            } elseif (is_cs_symbol($name)
                && next_significant($tokens, $i) === '('
                && !(is_array($prevSig) && in_array(
                    $prevSig[0],
                    [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON],
                    true
                ))
                && !guarded($open, $name)) {
                // A call, not a definition or a method. `\cs__x()` is included on
                // purpose: a leading separator means the global function, which is
                // exactly what this check is about.
                $reported[] = [$t[2], $name, 'call'];
            }
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
