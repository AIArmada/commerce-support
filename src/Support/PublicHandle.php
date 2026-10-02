<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Support\Str;

final class PublicHandle
{
    public static function normalize(string $handle): string
    {
        return mb_strtolower(mb_trim($handle));
    }

    /** @return list<string> */
    public static function rules(): array
    {
        return ['required', 'string', 'min:3', 'max:40', 'regex:/\A[a-z0-9]+(?:[-_][a-z0-9]+)*\z/', 'not_in:admin,api,go,support,system'];
    }

    public static function generate(string $name): string
    {
        $base = mb_substr(Str::slug($name), 0, 27);
        $base = mb_rtrim($base, '-');

        return ($base !== '' ? $base : 'creator') . '-' . mb_strtolower(Str::random(10));
    }

    public static function forIdentity(string $identity): string
    {
        return 'creator-' . mb_substr(hash('sha256', $identity), 0, 24);
    }
}
