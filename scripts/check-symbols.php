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
 * What counts as a guard. A `function_exists('cs__x')` test protects a call only when
 * it makes the symbol's existence *certain* at that point:
 *   - every test in the condition contributes, not just the last, because `&&`-joined
 *     tests all hold when the body runs;
 *   - `||` and `xor` destroy that guarantee for every operand, but only at the paren
 *     level the test itself is evaluated at -- inside a nested group they are the
 *     group's business, not the test's;
 *   - a negated test (`! function_exists(...)`) guarantees the opposite, so it is not
 *     a guard at all;
 *   - the body may be a `{ }` block, a template `: ... endif;` region, a brace-less
 *     single statement, or a `? :` consequent -- and an `else`/`elseif` branch is not
 *     protected by the branch before it;
 *   - a test used as a value, or one whose result is then combined with an operator
 *     (`function_exists('x') . f()`), guards nothing.
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
 * Could this token begin a statement, or does it continue the expression before it?
 *
 * The distinction decides whether a guard's body has started. `function_exists('x') . f()`
 * continues an expression, so f() is not guarded; `function_exists('x') ? a : b` and
 * `if ( ... ) f()` are.
 */
function starts_statement(mixed $t): bool
{
    if (!is_array($t)) {
        return in_array($t, ['(', '[', '!', '@', '$', '~'], true);
    }
    static $continues = [
        T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL,
        T_IS_SMALLER_OR_EQUAL, T_IS_GREATER_OR_EQUAL, T_SPACESHIP,
        T_COALESCE, T_COALESCE_EQUAL, T_CONCAT_EQUAL,
        T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR,
        T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_ARROW, T_DOUBLE_COLON,
        T_SL, T_SR, T_POW, T_INC, T_DEC,
        T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_MOD_EQUAL,
        T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_POW_EQUAL,
        T_NS_SEPARATOR, T_ELLIPSIS, T_ATTRIBUTE, T_AS, T_INSTANCEOF,
    ];
    return !in_array($t[0], $continues, true);
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
    $sawOr = false;      // the condition is `||`/`xor`-joined at the test's own level
    $condOrParen = -1;   // paren level of the last `||` seen since the last statement
    $ctrlParen = -1;     // paren depth at the innermost control keyword
    $ctrlPending = false;// that keyword's condition has not closed yet
    $ctrlReady = false;  // it has closed: the next token opens (or is) its body
    $prevSig = null;     // last significant token, for call context

    $endKeywords = [T_ENDIF, T_ENDWHILE, T_ENDFOR, T_ENDFOREACH, T_ENDSWITCH];
    $controls = [T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH];

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
            if ($ctrlPending && $paren === $ctrlParen) {
                $ctrlReady = true;
                $ctrlPending = false;
            }
        }

        // --- 2. a control keyword starts a condition whose body may be alt-form
        if (is_array($t) && in_array($t[0], $controls, true)) {
            $ctrlParen = $paren;
            $ctrlPending = true;
            $ctrlReady = false;
        }

        // --- 3. `else` / `elseif` end the branch before them -------------------
        // An alt-form `else:` runs when the test was FALSE, so the guard the `if`
        // branch opened must close here. Only alt-form: in block form the `}` that
        // precedes the `else` has already closed the branch, and popping again would
        // close an enclosing alt guard that is still live.
        if (is_array($t) && ($t[0] === T_ELSE || $t[0] === T_ELSEIF)) {
            if ($prevSig !== '}' && $open && end($open)['kind'] === 'alt') {
                array_pop($open);
            }
            if ($t[0] === T_ELSE) {
                $ctrlReady = true;   // `else :` opens a body of its own
            }
        }

        // --- 4. `||` and `xor` destroy the guarantee --------------------------
        // Only at the level the test is evaluated at: inside a nested group they are
        // that group's business, so `f('x') && ( $a || $b )` is still a guard.
        if (is_array($t) && in_array($t[0], [T_BOOLEAN_OR, T_LOGICAL_OR, T_LOGICAL_XOR], true)) {
            if ($pending) {
                if ($paren <= $pendingParen) {
                    $sawOr = true;
                }
            } else {
                $condOrParen = $paren;
            }
        }

        // --- 5. the condition has closed: this token opens, or is, its body ----
        // `$paren < $pendingParen` when the test sits inside an enclosing group
        // (`if ( ... )`); `$pendingParen === 0` means the test is the whole expression
        // (`function_exists('cs__x') ? ... : ...`), which closes on its own `)`.
        $guardReady = $pending
            && ($paren < $pendingParen || ($pendingParen === 0 && $paren === 0));
        if ($t !== ')' && ($guardReady || $ctrlReady)) {
            $kind = null;
            if ($t === '{') {
                $kind = 'block';
                $depth++;
            } elseif ($t === ':') {
                $kind = 'alt';
            } elseif ($t === ';' || $t === ',') {
                $kind = null;       // the test was used as a value
            } elseif ($guardReady && $t === '?') {
                $kind = 'stmt';     // the consequent of `test ? a : b` is guarded
            } elseif ($guardReady && starts_statement($t)) {
                $kind = 'stmt';
            }
            if ($kind !== null && !($guardReady && $sawOr)) {
                $open[] = [
                    'names' => $guardReady ? $pending : [],
                    'depth' => $kind === 'alt' ? $depth + 1 : $depth,
                    'kind'  => $kind,
                ];
            }
            $pending = [];
            $pendingParen = -1;
            $sawOr = false;
            $condOrParen = -1;
            $ctrlReady = false;
            if ($kind === 'block' || $kind === 'alt') {
                $prevSig = $t;
                continue;           // `{` is already counted; `:` opens nothing else
            }
        }

        // --- 6. ordinary token handling ---------------------------------------
        if ($t === '{') {
            $depth++;
            $condOrParen = -1;
        } elseif ($t === '}') {
            while ($open && end($open)['kind'] !== 'alt' && end($open)['depth'] === $depth) {
                array_pop($open);
            }
            $depth--;
            $condOrParen = -1;
        } elseif ($t === ';') {
            while ($open && end($open)['kind'] === 'stmt' && end($open)['depth'] === $depth) {
                array_pop($open);
            }
            // A `;` inside a `for ( ... ; ... ; ... )` header separates clauses; it
            // does not end a statement, so a guard condition there must survive it.
            if ($paren === 0) {
                $pending = [];
                $sawOr = false;
                $condOrParen = -1;
            }
            $ctrlReady = false;
        } elseif ($t === ':') {
            while ($open && end($open)['kind'] === 'stmt') {
                array_pop($open);   // the `?` consequent ends here
            }
        } elseif (is_array($t) && in_array($t[0], $endKeywords, true)) {
            if ($open && end($open)['kind'] === 'alt') {
                array_pop($open);
            }
            $ctrlReady = false;
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
            // Deliberately not guard-aware: `function_exists()` proves a *function*
            // exists, not the class a `new` constructs. Use `class_exists()` for that,
            // and let this check say so.
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
                $negated = ($prevSig === '!');
                $j = $i + 1;
                while ($j < $n && is_skippable($tokens[$j])) {
                    $j++;
                }
                if (!$negated && $j < $n && $tokens[$j] === '(') {
                    $k = $j + 1;
                    while ($k < $n && is_skippable($tokens[$k])) {
                        $k++;
                    }
                    if ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_CONSTANT_ENCAPSED_STRING) {
                        $inner = trim($tokens[$k][1], "'\"");
                        if (is_cs_symbol($inner)) {
                            if (!$pending) {
                                $pendingParen = $paren;
                                $sawOr = $condOrParen === $paren;
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
