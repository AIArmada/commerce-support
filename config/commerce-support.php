<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\NullOwnerResolver;

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'morph_key_type' => env('COMMERCE_MORPH_KEY_TYPE', 'uuid'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'resolver' => env('COMMERCE_OWNER_RESOLVER', NullOwnerResolver::class),
    ],
];
