<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

class Optimizer_Image_Generator
{
    public static function getCapabilities()
    {
        return array(
            'webp' => self::supportsFormat('webp'),
            'avif' => self::supportsFormat('avif'),
            'gd' => extension_loaded('gd'),
            'imagick' => class_exists('Imagick', false) || class_exists('Imagick')
        );
    }

    public static function needsGenerationForSettings($source, array $settings)
    {
        if (!is_file($source) || !is_readable($source)) {
            return false;
        }

        $maxBytes = max(1, (int) $settings['image_max_source_mb']) * 1024 * 1024;
        if (filesize($source) > $maxBytes) {
            return false;
        }

        foreach (self::getFormats($settings) as $format => $quality) {
            if (!self::supportsFormat($format)) {
                continue;
            }

            $target = preg_replace('/\.(jpe?g|png)$/i', '.' . $format, $source);
            if (self::needsGeneration($source, $target)) {
                return true;
            }
        }

        return false;
    }

    public static function generate($source, array $settings)
    {
        $result = array('generated' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => array());
        $maxBytes = max(1, (int) $settings['image_max_source_mb']) * 1024 * 1024;

        if (!is_file($source) || !is_readable($source)) {
            $result['failed']++;
            $result['errors'][] = 'Исходный файл недоступен: ' . self::relative($source);
            return $result;
        }

        if (filesize($source) > $maxBytes) {
            $result['skipped']++;
            return $result;
        }

        foreach (self::getFormats($settings) as $format => $quality) {
            if (!self::supportsFormat($format)) {
                $result['failed']++;
                $result['errors'][] = strtoupper($format) . ' не поддерживается сервером.';
                continue;
            }

            $target = preg_replace('/\.(jpe?g|png)$/i', '.' . $format, $source);

            if (!self::needsGeneration($source, $target)) {
                $result['skipped']++;
                continue;
            }

            $ok = self::generateOne($source, $target, $format, $quality);
            if ($ok) {
                $result['generated']++;
            }
            else {
                $result['failed']++;
                $result['errors'][] = 'Не удалось создать ' . self::relative($target);
            }
        }

        return $result;
    }

    protected static function getFormats(array $settings)
    {
        $formats = array();

        if (!empty($settings['image_generate_webp'])) {
            $formats['webp'] = max(1, min(100, (int) $settings['image_webp_quality']));
        }

        if (!empty($settings['image_generate_avif'])) {
            $formats['avif'] = max(1, min(100, (int) $settings['image_avif_quality']));
        }

        return $formats;
    }

    protected static function needsGeneration($source, $target)
    {
        return !is_file($target) || filemtime($source) > filemtime($target);
    }

    protected static function generateOne($source, $target, $format, $quality)
    {
        $tmp = $target . '.optimizer-' . getmypid() . '-' . mt_rand(1000, 9999) . '.tmp';
        $ok = false;

        if (self::imagickSupports($format)) {
            $ok = self::generateImagick($source, $tmp, $format, $quality);
        }
        elseif (self::gdSupports($format)) {
            $ok = self::generateGd($source, $tmp, $format, $quality);
        }

        if (!$ok || !is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            return false;
        }

        @chmod($tmp, 0644);

        if (!@rename($tmp, $target)) {
            @unlink($target);
            if (!@rename($tmp, $target)) {
                @unlink($tmp);
                return false;
            }
        }

        return true;
    }

    protected static function generateImagick($source, $target, $format, $quality)
    {
        try {
            $image = new Imagick();
            $image->readImage($source);

            if (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }

            $image->setImageFormat($format);
            $image->setImageCompressionQuality($quality);

            if ($format === 'avif') {
                $image->setOption('heic:speed', '6');
            }

            if (method_exists($image, 'stripImage')) {
                $image->stripImage();
            }

            $ok = $image->writeImage($target);
            $image->clear();
            $image->destroy();

            return (bool) $ok;
        }
        catch (Exception $e) {
            return false;
        }
    }

    protected static function generateGd($source, $target, $format, $quality)
    {
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $image = null;

        if (($extension === 'jpg' || $extension === 'jpeg') && function_exists('imagecreatefromjpeg')) {
            $image = @imagecreatefromjpeg($source);
        }
        elseif ($extension === 'png' && function_exists('imagecreatefrompng')) {
            $image = @imagecreatefrompng($source);
            if ($image) {
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
        }

        if (!$image) {
            return false;
        }

        if ($format === 'webp') {
            $ok = @imagewebp($image, $target, $quality);
        }
        else {
            $ok = @imageavif($image, $target, $quality);
        }

        imagedestroy($image);
        return (bool) $ok;
    }

    protected static function supportsFormat($format)
    {
        return self::imagickSupports($format) || self::gdSupports($format);
    }

    protected static function gdSupports($format)
    {
        return $format === 'webp' ? function_exists('imagewebp') : function_exists('imageavif');
    }

    protected static function imagickSupports($format)
    {
        if (!class_exists('Imagick', false) && !class_exists('Imagick')) {
            return false;
        }

        try {
            $formats = Imagick::queryFormats(strtoupper($format));
            return is_array($formats) && count($formats) > 0;
        }
        catch (Exception $e) {
            return false;
        }
    }

    protected static function relative($path)
    {
        $base = rtrim(str_replace('\\', '/', CMS_FOLDER), '/');
        $path = str_replace('\\', '/', $path);
        return strpos($path, $base . '/') === 0 ? substr($path, strlen($base)) : $path;
    }
}
