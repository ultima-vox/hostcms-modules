<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimizer_Image_Scanner
{
    public static function scan($paths)
    {
        $result = array();
        $base = realpath(CMS_FOLDER);

        if ($base === false) {
            return $result;
        }

        foreach (self::parsePaths($paths) as $relativePath) {
            $absolutePath = realpath(CMS_FOLDER . ltrim($relativePath, '/\\'));

            if ($absolutePath === false || !is_dir($absolutePath) || !self::isInside($absolutePath, $base)) {
                continue;
            }

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $fileInfo) {
                    if (!$fileInfo->isFile() || $fileInfo->isLink()) {
                        continue;
                    }

                    $path = $fileInfo->getPathname();
                    $extension = strtolower($fileInfo->getExtension());

                    if (!in_array($extension, array('jpg', 'jpeg', 'png'), true)) {
                        continue;
                    }

                    if (strpos(str_replace('\\', '/', $path), '/upload/optimizer_cache/') !== false) {
                        continue;
                    }

                    $result[] = $path;
                }
            }
            catch (UnexpectedValueException $e) {
                continue;
            }
        }

        $result = array_values(array_unique($result));
        sort($result, SORT_STRING);

        return $result;
    }

    protected static function parsePaths($paths)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $paths);
        $result = array();

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || substr($line, 0, 1) === '#') {
                continue;
            }

            $line = str_replace('\\', '/', $line);
            $line = trim($line, '/');

            if ($line !== '' && strpos($line, '..') === false) {
                $result[] = $line;
            }
        }

        return $result;
    }

    protected static function isInside($path, $base)
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $base = rtrim(str_replace('\\', '/', $base), '/');

        return $path === $base || strpos($path . '/', $base . '/') === 0;
    }
}
