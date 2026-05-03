<?php
/**
 * Axent - Lang.php
 * Système de traduction multilingue (fr, en, es, de)
 */

declare(strict_types=1);

class Lang
{
    private static array $translations = [];
    private static string $currentLang = 'fr';

    public static function init(string $lang = 'fr'): void
    {
        $lang = in_array($lang, LANG_AVAILABLE) ? $lang : LANG_DEFAULT;
        self::$currentLang = $lang;

        $file = __DIR__ . '/../lang/' . $lang . '/messages.php';
        $fallbackFile = __DIR__ . '/../lang/' . LANG_FALLBACK . '/messages.php';

        if (file_exists($file)) {
            self::$translations = require $file;
        } elseif (file_exists($fallbackFile)) {
            self::$translations = require $fallbackFile;
        }
    }

    public static function get(string $key, array $replace = []): string
    {
        $translation = self::$translations[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $translation = str_replace(':' . $k, $v, $translation);
        }
        return $translation;
    }

    public static function current(): string
    {
        return self::$currentLang;
    }

    public static function detectFromBrowser(): string
    {
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        foreach (LANG_AVAILABLE as $lang) {
            if (str_contains(strtolower($accept), $lang)) return $lang;
        }
        return LANG_DEFAULT;
    }
}

// Alias court
function __($key, array $replace = []): string {
    return Lang::get($key, $replace);
}
