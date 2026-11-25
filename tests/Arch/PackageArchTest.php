<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\CommerceSupport')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\CommerceSupport');

arch('package source never imports host application code')
    ->expect('AIArmada\CommerceSupport')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\CommerceSupport\Contracts', 'AIArmada\CommerceSupport\Data'])
    ->not->toUse([
        'AIArmada\CommerceSupport\Actions',
        'AIArmada\CommerceSupport\Models',
        'AIArmada\CommerceSupport\Drivers',
        'AIArmada\CommerceSupport\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
