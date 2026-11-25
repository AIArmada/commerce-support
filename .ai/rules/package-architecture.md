---
paths:
  - src/**
  - config/**
  - tests/**
---

# Laravel Package Architecture Rules (`AIArmada\CommerceSupport`)

## Layout
This package uses **modular layout (`src/Features/*`)**. Every feature declares `feature.json`; cross-feature imports go strictly through `Features/<Name>/Api/`.

## Package-Safety Laws
- Never import host application code (`App\\*`) inside `src/`.
- Never mutate host globals (`Model::shouldBeStrict()`, `Model::unguard()`, `DB::prohibitDestructiveCommands()`, `Date::use()`).
- Never call `env()` outside `config/`, `sleep()`/`Sleep::sleep()`, `dd()`/`dump()`/`ray()`, or add inline `@phpstan-ignore` suppressions.
- Never keep backward-compatibility shims, legacy wrappers, or leftover dead files; update callers and delete superseded code in the same change.
- Keep `src/Contracts/` and `src/Data/` free of Eloquent models, `Illuminate\\Http\\*`, Actions, and Drivers.
- Run `composer arch:check`, `vendor/bin/phpstan analyse`, `vendor/bin/pest`, and `vendor/bin/pint --test` before declaring done.
