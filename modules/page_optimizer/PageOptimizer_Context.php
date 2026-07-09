<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Page Optimizer - request and response safety checks.
 */
class PageOptimizer_Context
{
    /**
     * Returns true only when the current response is safe for HTML post-processing.
     *
     * This method intentionally rejects uncertain contexts. The optimizer must not
     * process admin pages, AJAX responses, non-GET requests, XML/JSON/RSS output,
     * or partial fragments where rewriting can break application behaviour.
     *
     * @param string $html
     * @return bool
     */
    public static function shouldProcess($html)
    {
        if (!is_string($html) || trim($html) === '') {
            return false;
        }

        if (PHP_SAPI === 'cli') {
            return false;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'GET' && $method !== 'HEAD') {
            return false;
        }

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && preg_match('#^/(admin|hostcmsfiles|modules)/#i', $uri)) {
            return false;
        }

        if (self::isAjaxRequest()) {
            return false;
        }

        if (self::hasNonHtmlContentType()) {
            return false;
        }

        if (self::looksLikeNonHtmlPayload($html)) {
            return false;
        }

        return self::looksLikeFullHtmlDocument($html);
    }

    protected static function isAjaxRequest()
    {
        if (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        return strpos($accept, 'application/json') !== false && strpos($accept, 'text/html') === false;
    }

    protected static function hasNonHtmlContentType()
    {
        $contentType = '';

        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = strtolower(trim(substr($header, 13)));
                break;
            }
        }

        if ($contentType === '') {
            return false;
        }

        return strpos($contentType, 'text/html') === false
            && strpos($contentType, 'application/xhtml+xml') === false;
    }

    protected static function looksLikeNonHtmlPayload($html)
    {
        $sample = ltrim(substr($html, 0, 512));

        if ($sample === '') {
            return true;
        }

        if ($sample[0] === '{' || $sample[0] === '[') {
            return true;
        }

        return stripos($sample, '<?xml') === 0
            || stripos($sample, '<rss') === 0
            || stripos($sample, '<feed') === 0
            || stripos($sample, '<sitemapindex') === 0
            || stripos($sample, '<urlset') === 0;
    }

    protected static function looksLikeFullHtmlDocument($html)
    {
        return stripos($html, '<html') !== false
            && stripos($html, '</html>') !== false
            && stripos($html, '<head') !== false
            && stripos($html, '</head>') !== false
            && stripos($html, '<body') !== false
            && stripos($html, '</body>') !== false;
    }
}
