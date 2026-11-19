<?php

namespace NextSafar;

if (!defined('NEXTSAFAR_PATH')) exit;

/**
 * PSR-4 Autoloader — با پشتیبانی از پیشوند «class-» در نام فایل‌ها.
 * اکثر فایل‌های پلاگین class-*.php هستند؛ نسخه قبلی فقط *.php را می‌گشت
 * و باعث Fatal «Class not found» برای ApiCache / GeminiQuota / AviationClient می‌شد.
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'NextSafar\\') !== 0) {
        return;
    }

    $relative = substr($class, strlen('NextSafar\\'));
    if ($relative === '') {
        return;
    }

    $parts     = explode('\\', $relative);
    $className = array_pop($parts);

    $kebab = static function ($s) {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $s));
    };

    $subPath = '';
    if (!empty($parts)) {
        $subPath = implode('/', array_map($kebab, $parts)) . '/';
    }

    $base = $kebab($className);
    $root = NEXTSAFAR_PATH . 'inc/';

    $candidates = [
        $root . $subPath . 'class-' . $base . '.php',
        $root . $subPath . $base . '.php',
        $root . 'class-' . $base . '.php',
        $root . $base . '.php',
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});