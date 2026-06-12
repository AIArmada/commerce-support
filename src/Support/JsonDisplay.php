<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

final class JsonDisplay
{
    public static function format(mixed $state): string
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $state = $decoded;
            }
        }

        return '<pre>' . e(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '') . '</pre>';
    }
}
