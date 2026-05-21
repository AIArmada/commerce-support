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
    | Defaults
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'default' => env('COMMERCE_DEFAULT_CURRENCY', 'MYR'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'enabled' => env('COMMERCE_OWNER_ENABLED', false),
        'resolver' => env('COMMERCE_OWNER_RESOLVER', NullOwnerResolver::class),
    ],

    'health' => [
        'view_ability' => 'viewCommerceHealth',
    ],
];
