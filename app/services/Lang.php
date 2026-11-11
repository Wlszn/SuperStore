<?php
declare(strict_types=1);

namespace App\Services;

/**
 * English / French interface (bonus feature).
 * The language is kept in the session and changed with GET /lang?to=fr.
 * Text lives in app/lang/en.php and app/lang/fr.php as 'key' => 'text'.
 */
final class Lang
{
    public const SUPPORTED = ['en', 'fr'];

    /** @var array<string, array<string, string>> */
    private static array $loaded = [];

    public static function current(): string
    {
        $lang = $_SESSION['lang'] ?? 'en';

        return in_array($lang, self::SUPPORTED, true) ? $lang : 'en';
    }

    public static function set(string $lang): void
    {
        if (in_array($lang, self::SUPPORTED, true)) {
            $_SESSION['lang'] = $lang;
        }
    }

    /** The text for $key in the current language; {name} placeholders are filled from $vars. */
    public static function t(string $key, array $vars = []): string
    {
        $text = self::strings(self::current())[$key] ?? self::strings('en')[$key] ?? $key;

        foreach ($vars as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    /** Every key starting with $prefix, for the JavaScript on a page. */
    public static function forScript(string $prefix): array
    {
        $out = [];
        foreach (self::strings('en') as $key => $text) {
            if (str_starts_with($key, $prefix)) {
                $out[substr($key, strlen($prefix))] = self::t($key);
            }
        }

        return $out;
    }

    private static function strings(string $lang): array
    {
        return self::$loaded[$lang] ??= require APP_ROOT . '/app/lang/' . $lang . '.php';
    }
}
