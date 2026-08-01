<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Support\Str;

final class StringSimilarity
{
    public static function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/i', ' ')
            ->trim()
            ->value();
    }

    public static function score(string $search, string $candidate): float
    {
        if ($search === '' || $candidate === '') {
            return 0.0;
        }

        $maxLength = max(mb_strlen($search), mb_strlen($candidate));
        $distanceScore = $maxLength === 0
            ? 0.0
            : 1 - (levenshtein($search, $candidate) / $maxLength);

        similar_text($search, $candidate, $similarityPercent);
        $similarityScore = $similarityPercent / 100;

        return max($distanceScore, $similarityScore);
    }
}
