<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

if (!defined('OPTIMIZER_AUTOLOAD_REGISTERED')) {
    define('OPTIMIZER_AUTOLOAD_REGISTERED', true);

    spl_autoload_register(function ($className) {
        if ($className !== 'Optimizer' && strpos($className, 'Optimizer_') !== 0) {
            return;
        }

        $path = __DIR__ . '/classes/' . $className . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    });
}
