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
        'tables' => [
            'saved_searches' => env('COMMERCE_SUPPORT_TABLE_SAVED_SEARCHES', 'saved_searches'),
            'reports' => env('COMMERCE_SUPPORT_TABLE_REPORTS', 'reports'),
            'notification_preferences' => env('COMMERCE_SUPPORT_TABLE_NOTIFICATION_PREFERENCES', 'notification_preferences'),

        ],
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
        'team_type' => env('COMMERCE_OWNER_TEAM_TYPE'),
    ],

    'health' => [
        'view_ability' => 'viewCommerceHealth',
    ],

    'filament' => [
        'navigation' => [
            'enabled' => true,
            'groups' => [],
            'packages' => [],
            'items' => [],
        ],
    ],
];
