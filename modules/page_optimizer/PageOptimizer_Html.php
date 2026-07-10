<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Conservative HTML minifier.
 *
 * The minifier reduces formatting whitespace without changing whitespace
 * inside elements where it can be semantically significant.
 */
class PageOptimizer_Html
{
    /**
     * @param string $html
     * @param array $options
     * @return string
     */
    public static function minify($html, array $options = [])
    {
        if (!is_string($html) || $html === '') {
            return $html;
        }

        $options += [
            'remove_comments' => false,
        ];

        $tokens = [];
        $protectedHtml = self::protectSensitiveBlocks($html, $tokens);

        if ($protectedHtml === null) {
            return $html;
        }

        if (!empty($options['remove_comments'])) {
            $protectedHtml = self::removeSafeComments($protectedHtml);

            if ($protectedHtml === null) {
                return $html;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $protectedHtml);

        if (!is_array($lines)) {
            return $html;
        }

        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line !== '') {
                $result[] = $line;
            }
        }

        $minified = implode(' ', $result);

        return $tokens ? strtr($minified, $tokens) : $minified;
    }

    /**
     * Protect content whose whitespace may be significant.
     *
     * @param string $html
     * @param array $tokens
     * @return string|null
     */
    protected static function protectSensitiveBlocks($html, array &$tokens)
    {
        $pattern = '#<(script|style|pre|textarea|template|code|svg|math)\b[^>]*>.*?</\1\s*>#siu';

        return preg_replace_callback($pattern, function ($match) use (&$tokens, $html) {
            $token = self::createToken(count($tokens), $html);
            $tokens[$token] = $match[0];

            return $token;
        }, $html);
    }

    /**
     * Remove ordinary development comments while preserving comments that can
     * affect browser behaviour, SEO, templates or third-party integrations.
     *
     * @param string $html
     * @return string|null
     */
    protected static function removeSafeComments($html)
    {
        return preg_replace_callback('/<!--(.*?)-->/su', function ($match) {
            $comment = trim($match[1]);

            if (self::mustPreserveComment($comment)) {
                return $match[0];
            }

            return '';
        }, $html);
    }

    protected static function mustPreserveComment($comment)
    {
        if ($comment === '') {
            return false;
        }

        return preg_match(
            '/^(?:\[if\b|<!\[endif\]|noindex\b|\/noindex\b|ko\b|\/ko\b|googleoff:|googleon:|page-optimizer:|#include\b|#echo\b|#if\b|#endif\b)/i',
            $comment
        ) === 1;
    }

    protected static function createToken($index, $html)
    {
        $token = '___PAGE_OPTIMIZER_HTML_' . $index . '___';

        while (strpos($html, $token) !== false) {
            $token .= '_';
        }

        return $token;
    }
}
