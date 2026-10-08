<?php
// Languages. Interface texts are written in Portuguese inside __() and translated by {locale}.php files:
// core/lang/ for the core, and a lang/ folder in each theme and plugin for their own texts.
//
// Each site has a main language (chosen in the installer, changeable in "Aparência e contato"): the site,
// its example content and the panel speak it. Each user may pick another language for the panel only.
//
// A site may also be offered in extra languages. Their pages are translations of the main language's pages
// (pages.translation_of) and live under a prefix: /sobre in the main language, /en-us/about, /es-es/nosotros.
// Menus follow the translations, and texts in "Aparência e contato" can be translated too.

const PB_LOCALES = ['pt-BR' => 'Português (Brasil)', 'en' => 'English', 'es' => 'Español'];
/** Address prefix of each language when it isn't the site's main one. */
const PB_LOCALE_PATHS = ['pt-BR' => 'pt-br', 'en' => 'en-us', 'es' => 'es-es'];

/** The site's languages: the main one first, then the extra ones switched on in the panel. */
function pb_site_locales(): array
{
    $main = pb_site_locale();
    $extra = json_decode((string) pb_option('locales', '[]'), true) ?: [];
    return [$main, ...array_values(array_filter(array_keys(PB_LOCALES), fn($l) => $l !== $main && in_array($l, $extra, true)))];
}

/** Switches extra languages on or off. Their pages stay saved while a language is off, and come back with it. */
function pb_set_site_locales(array $extra): void
{
    $extra = array_values(array_filter(array_keys(PB_LOCALES), fn($l) => $l !== pb_site_locale() && in_array($l, $extra, true)));
    pb_set_option('locales', json_encode($extra));
}

/** The language of the content being shown: the main one, or the extra language of the address (/en-us/...). */
function pb_content_locale(): string
{
    return $GLOBALS['pb_content_locale'] ?? pb_site_locale();
}

/** An address of the site in a language, without the install folder: '/about' → '/en-us/about' in an extra language. */
function pb_locale_path(string $locale, string $path = '/'): string
{
    if ($locale === pb_site_locale() || !isset(PB_LOCALE_PATHS[$locale])) {
        return $path;
    }
    return '/' . PB_LOCALE_PATHS[$locale] . ($path === '/' ? '' : $path);
}

function pb_locale(): string
{
    return $GLOBALS['pb_locale'] ?? 'pt-BR';
}

/** The site's language (pages, theme, plugins, example content). */
function pb_site_locale(): string
{
    $locale = pb_option('locale', 'pt-BR');
    return isset(PB_LOCALES[$locale]) ? $locale : 'pt-BR';
}

/** Switches the language of interface texts; translations already loaded by themes and plugins follow. */
function pb_set_locale(string $locale): void
{
    $GLOBALS['pb_locale'] = isset(PB_LOCALES[$locale]) ? $locale : 'pt-BR';
    $GLOBALS['pb_translations'] = [];
    pb_load_translations(PB_ROOT . '/core/lang', false);
    foreach ($GLOBALS['pb_translation_dirs'] ?? [] as $dir) {
        pb_load_translations($dir, false);
    }
}

/** Adds the translations in $dir/{locale}.php. Themes and plugins get theirs loaded from their lang/ folder. */
function pb_load_translations(string $dir, bool $remember = true): void
{
    if ($remember) {
        $GLOBALS['pb_translation_dirs'][$dir] = $dir;
    }
    $file = "$dir/" . pb_locale() . '.php';
    if (pb_locale() !== 'pt-BR' && is_file($file)) {
        $GLOBALS['pb_translations'] = ($GLOBALS['pb_translations'] ?? []) + (require $file);
    }
}

/** The best of our languages for the visitor's browser (used by the installer before anything is chosen). */
function pb_browser_locale(): string
{
    foreach (explode(',', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
        $tag = trim(explode(';', $part)[0]);
        if (str_starts_with($tag, 'pt')) {
            return 'pt-BR';
        }
        if (str_starts_with($tag, 'es')) {
            return 'es';
        }
        if (str_starts_with($tag, 'en')) {
            return 'en';
        }
    }
    return 'pt-BR';
}

/** A date (and time) written the way the current language writes it. */
function pb_date(string $datetime, bool $withTime = false): string
{
    $timestamp = strtotime($datetime) ?: time();
    $english = pb_locale() === 'en';
    return date(($english ? 'm/d/Y' : 'd/m/Y') . ($withTime ? ($english ? ' g:i A' : ' H:i') : ''), $timestamp);
}

/** Language tag for social networks (og:locale), e.g. "pt-BR" => "pt_BR". */
function pb_og_locale(string $locale): string
{
    return ['pt-BR' => 'pt_BR', 'en' => 'en_US', 'es' => 'es_ES'][$locale] ?? 'pt_BR';
}

/** Changes the site's main language; its pages are marked with it so they keep being found. */
function pb_set_site_locale(string $locale): void
{
    if (!isset(PB_LOCALES[$locale])) {
        throw new InvalidArgumentException(__('Idioma inválido.'));
    }
    $translations = pb_db()->prepare('SELECT COUNT(*) FROM ' . pb_table('pages') . ' WHERE locale = ? AND translation_of IS NOT NULL');
    $translations->execute([$locale]);
    if ($translations->fetchColumn() > 0) {
        throw new InvalidArgumentException(sprintf(__('O site já tem páginas traduzidas para %s. Exclua essas traduções antes de tornar este o idioma principal.'), PB_LOCALES[$locale]));
    }
    pb_db()->prepare('UPDATE ' . pb_table('pages') . ' SET locale = ? WHERE translation_of IS NULL')->execute([$locale]);
    pb_set_option('locale', $locale);
    pb_set_site_locales(array_diff(pb_site_locales(), [$locale]));
}

/** A manifest's name or description in the current language: plugin.json and theme.json may carry "i18n": {"en": {...}}. */
function pb_manifest_text(array $manifest, string $key): string
{
    return (string) ($manifest['i18n'][pb_locale()][$key] ?? $manifest[$key] ?? '');
}
