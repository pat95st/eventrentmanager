<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Sehr dünne, framework-freie Übersetzungsschicht: lädt einmal pro
 * Locale die flache Schlüssel-Wert-Übersetzungsdatei aus
 * resources/lang/{locale}.php (z. B. resources/lang/de.php) und cached sie
 * pro Request. Die Locale selbst kommt aus App\Infrastructure\Http\Locale
 * (analog zu CurrentUser/LegalFooterLinks), damit kein Aufrufer sie
 * durchreichen muss.
 *
 * Fällt eine Übersetzung in der aktiven Sprache aus, wird zuerst auf die
 * deutsche Übersetzungsdatei zurückgefallen (die "Referenzsprache" dieser
 * Anwendung), und erst wenn selbst dort der Schlüssel fehlt, wird der
 * Schlüssel selbst (in eckigen Klammern, gut erkennbar) angezeigt — es
 * kommt also NIE zu einem Fatal Error durch eine fehlende Übersetzung.
 */
final class Translator
{
    private static array $cache = [];

    /** @param array<string, string> $replacements */
    public static function translate(string $key, array $replacements = []): string
    {
        $locale = Locale::get();

        $value = self::lookup($locale, $key);
        if ($value === null && $locale !== Locale::DEFAULT) {
            $value = self::lookup(Locale::DEFAULT, $key);
        }
        if ($value === null) {
            // Kein Fatal Error, aber deutlich als fehlende Übersetzung
            // erkennbar (z. B. beim manuellen Durchklicken einer neuen Seite).
            $value = '[' . $key . ']';
        }

        if ($replacements === []) {
            return $value;
        }

        $search = [];
        $substitutes = [];
        foreach ($replacements as $placeholder => $replacement) {
            $search[] = '{' . $placeholder . '}';
            $substitutes[] = (string) $replacement;
        }

        return str_replace($search, $substitutes, $value);
    }

    private static function lookup(string $locale, string $key): ?string
    {
        $translations = self::load($locale);
        $value = $translations[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return array<string, string> */
    private static function load(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $path = __DIR__ . '/../../../resources/lang/' . $locale . '.php';
        $translations = is_file($path) ? require $path : [];

        return self::$cache[$locale] = is_array($translations) ? $translations : [];
    }
}

/**
 * Globaler Übersetzungs-Helfer für alle Views, analog zu e() (siehe
 * View.php): `use function App\Infrastructure\Http\t;` und dann z. B.
 * `t('nav.articles')` bzw. mit Platzhaltern `t('flash.rental_emailed', ['recipient' => $email])`.
 *
 * @param array<string, string|int> $replacements
 */
function t(string $key, array $replacements = []): string
{
    return Translator::translate($key, $replacements);
}
