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
        'json_column_type' => env('COMMERCE_SUPPORT_JSON_COLUMN_TYPE', 'jsonb'),
        'tables' => [
            'saved_searches' => env('COMMERCE_SUPPORT_TABLE_SAVED_SEARCHES', 'saved_searches'),
            'reports' => env('COMMERCE_SUPPORT_TABLE_REPORTS', 'reports'),
            'notification_preferences' => env('COMMERCE_SUPPORT_TABLE_NOTIFICATION_PREFERENCES', 'notification_preferences'),
            'languages' => env('COMMERCE_SUPPORT_TABLE_LANGUAGES', 'languages'),
            'currencies' => env('COMMERCE_SUPPORT_TABLE_CURRENCIES', 'currencies'),
            'timezones' => env('COMMERCE_SUPPORT_TABLE_TIMEZONES', 'timezones'),
            'tags' => env('COMMERCE_SUPPORT_TABLE_TAGS', 'tags'),
            'taggables' => env('COMMERCE_SUPPORT_TABLE_TAGGABLES', 'taggables'),

        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'default' => env('COMMERCE_DEFAULT_CURRENCY', 'MYR'),
        'exchange_rates' => [
            'base' => env('COMMERCE_EXCHANGE_RATE_BASE', 'USD'),
            'rates' => [],
        ],
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

    'targeting' => [
        'trust_proxy_headers' => env('COMMERCE_TARGETING_TRUST_PROXY_HEADERS', false),
    ],

    'filesystem' => [
        'disk' => env('COMMERCE_FILESYSTEM_DISK'),
    ],

    'health' => [
        'view_ability' => 'viewCommerceHealth',
    ],

    'filament' => [
        'navigation' => [
            'enabled' => true,
            'top_bar' => false,
            'groups' => [],
            'packages' => [],
            'items' => [],
        ],
    ],
];
