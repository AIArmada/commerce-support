<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use Spatie\Translatable\HasTranslations;

trait HasCommerceTranslations
{
    use HasTranslations;

    /**
     * Get a translation for the current app locale, falling back to the
     * first available translation if the locale is not set.
     */
    public function translate(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $translations = $this->getTranslations($field);

        if (isset($translations[$locale])) {
            return $translations[$locale];
        }

        $fallback = config('app.fallback_locale', 'en');

        if (isset($translations[$fallback])) {
            return $translations[$fallback];
        }

        return $translations[array_key_first($translations)] ?? null;
    }

    /**
     * Set a translation for a specific locale.
     */
    public function translateIn(string $field, string $value, string $locale): void
    {
        $translations = $this->getTranslations($field);
        $translations[$locale] = $value;
        $this->$field = $translations;
    }

    /**
     * Check if a field has a translation for the given locale.
     */
    public function hasTranslation(string $field, string $locale): bool
    {
        $translations = $this->getTranslations($field);

        return isset($translations[$locale]) && $translations[$locale] !== '';
    }
}
