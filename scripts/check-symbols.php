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
 * nor line-based, so a text scan cannot tell a call site from a mention inside a
 * comment, a string or a heredoc. Tokens can, and they do not guess.
 *
 * What counts as a call site. Three forms, and every one of them is lexical -- none
 * needs flow analysis, which is the whole point: six rounds of trying to reason about
 * `function_exists()` guards statically each leaked, and every leak was a missed fatal.
 *   - a plain call, `cs__x(`, including a fully qualified `\cs__x(`
 *   - a class construction, `new cs__Widget`
 *   - a string callback: a string literal whose entire content is a `cs__`/`CS_` name,
 *     as in `add_action('init', 'cs__foo')`. This is the dominant form in a WordPress
 *     theme -- the starter theme passes thirteen of them to add_action/add_filter --
 *     and WordPress fatals at runtime if the named function does not exist.
 * A method (`$o->cs__m()`), a static call (`Foo::cs__m()`), a docblock, a heredoc, and a
 * string that merely mentions the name are not call sites.
 *
 * Why a `function_exists()` test is NOT an exemption. Six rounds of trying to prove
 * statically that a `cs__` call is guarded each produced a replacement that leaked
 * somewhere new: an over-indented closer, a tab/space mix, `<?php } ?>`, a closer
 * behind a comment, a one-line guard, a brace-less body, `&&` versus `||`, a ternary
 * consequent, a guard in a `for` header, an alt-form `else:` branch, a test nested in
 * an enclosing group, and five more in the final round alone. Every one of those leaks
 * was a *missed fatal* in code that reads as ordinary in a WordPress template -- and a
 * missed fatal is the failure this check exists for: `Class "cs__primary_menu_walker"
 * not found` is what took the v3 theme down.
 *
 * So the rule is flat: report every `cs__` call whose symbol is defined nowhere in the
 * theme, guard or no guard. A false alarm costs one line of noise; a missed fatal costs
 * the site. If a call really does target another theme or plugin, say so where the call
 * is made -- `class_exists()` / `function_exists()` at the call site, or a stub -- rather
 * than asking a scanner to prove it on your behalf.
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
    $prevSig = null;

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (is_skippable($t)) {
            continue;
        }

        if (is_array($t) && ($t[0] === T_FUNCTION || $t[0] === T_CLASS
            || $t[0] === T_INTERFACE || $t[0] === T_TRAIT)) {
            // `function &cs__x()` returns by reference: the `&` sits between the
            // keyword and the name, and stepping past it by token index rather than
            // by "next significant" is what makes it land on the name.
            $j = $i + 1;
            while ($j < $n && is_skippable($tokens[$j])) {
                $j++;
            }
            // PHP 8.1 lexes the `&` of `function &cs__x()` as T_AMPERSAND_* rather than
            // as the single character '&', so match on the token's text.
            $isAmp = $j < $n
                && ($tokens[$j] === '&'
                    || (is_array($tokens[$j]) && $tokens[$j][1] === '&'));
            if ($isAmp) {
                $j++;
                while ($j < $n && is_skippable($tokens[$j])) {
                    $j++;
                }
            }
            if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING
                && is_cs_symbol($tokens[$j][1])) {
                $defined[$tokens[$j][1]] = true;
            }
        } elseif (is_array($t) && $t[0] === T_NEW) {
            $nx = next_significant($tokens, $i);
            if (is_array($nx) && $nx[0] === T_STRING && is_cs_symbol($nx[1])) {
                $reported[] = [$nx[2], $nx[1], 'new'];
            }
        } elseif (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) {
            // `add_action('init', 'cs__foo')` is a call site: WordPress calls
            // cs__foo() at runtime and fatals if it does not exist. This is lexical,
            // not flow analysis -- a string literal either names a cs__ symbol or it
            // does not -- so it cannot leak the way guard tracking did.
            $inner = trim($t[1], "'\"");
            // Only `cs__`, not `CS_`. The spec names `cs__` as the prefix for
            // functions and classes; `CS_` appears nowhere in it, and an
            // uppercase-underscore string is a CONSTANT name far more often than a
            // callback -- `define('CS_VERSION', ...)` would report as a callback the
            // moment anyone adds a version constant to a starter theme. Calls and
            // `new` still check both prefixes: an undefined `CS_foo()` is a fatal
            // whichever way you read it.
            if (preg_match('/^cs__\w+$/', $inner)) {
                $reported[] = [$t[2], $inner, 'callback'];
            }
        } elseif (is_array($t) && ($t[0] === T_STRING || $t[0] === T_NAME_FULLY_QUALIFIED)) {
            // PHP 8 lexes `\cs__x` as a single T_NAME_FULLY_QUALIFIED token rather than
            // T_NS_SEPARATOR + T_STRING, so the leading separator has to be stripped or
            // a fully qualified global call goes unseen.
            $name = ltrim($t[1], '\\');

            if (is_cs_symbol($name)
                && next_significant($tokens, $i) === '('
                && !(is_array($prevSig) && in_array(
                    $prevSig[0],
                    [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON],
                    true
                ))) {
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
