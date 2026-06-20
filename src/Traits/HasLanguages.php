<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasLanguages
{
    public function initializeHasLanguages(): void
    {
        $this->casts['languages'] = 'array';
    }

    public function hasLanguage(string $code): bool
    {
        return in_array($code, $this->languages ?? [], true);
    }

    public function scopeWithLanguage(Builder $query, string $code): Builder
    {
        return $query->whereJsonContains('languages', $code);
    }

    public function addLanguage(string $code): void
    {
        $languages = $this->languages ?? [];

        if (! in_array($code, $languages, true)) {
            $languages[] = $code;
            $this->languages = array_values($languages);
            $this->save();
        }
    }

    public function removeLanguage(string $code): void
    {
        $languages = $this->languages ?? [];
        $this->languages = array_values(
            array_filter($languages, fn (string $c): bool => $c !== $code),
        );
        $this->save();
    }

    public function syncLanguages(array $codes): void
    {
        $this->languages = array_values(array_unique($codes));
        $this->save();
    }
}
