<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimizer_Html
{
    public static function minify($html, array $options = array())
    {
        if (!is_string($html) || $html === '') {
            return $html;
        }

        $options += array('remove_comments' => false);
        $tokens = array();
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

        $result = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $result[] = $line;
            }
        }

        $minified = implode(' ', $result);
        return $tokens ? strtr($minified, $tokens) : $minified;
    }

    protected static function protectSensitiveBlocks($html, array &$tokens)
    {
        $pattern = '#<(script|style|pre|textarea|template|code|svg|math)\b[^>]*>.*?</\1\s*>#siu';
        return preg_replace_callback($pattern, function ($match) use (&$tokens, $html) {
            $token = self::createToken(count($tokens), $html);
            $tokens[$token] = $match[0];
            return $token;
        }, $html);
    }

    protected static function removeSafeComments($html)
    {
        return preg_replace_callback('/<!--(.*?)-->/su', function ($match) {
            $comment = trim($match[1]);
            return self::mustPreserveComment($comment) ? $match[0] : '';
        }, $html);
    }

    protected static function mustPreserveComment($comment)
    {
        if ($comment === '') {
            return false;
        }

        $lower = strtolower($comment);
        $prefixes = array('[if', '<![endif', 'noindex', '/noindex', 'ko ', '/ko', 'googleoff:', 'googleon:', 'optimizer:', '#include', '#echo', '#if', '#endif');
        foreach ($prefixes as $prefix) {
            if (strpos($lower, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    protected static function createToken($index, $html)
    {
        $token = '___OPTIMIZER_HTML_' . $index . '___';
        while (strpos($html, $token) !== false) {
            $token .= '_';
        }
        return $token;
    }
}
