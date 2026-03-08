<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Testing;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract tests for models using HasOwner trait.
 *
 * Use this trait to verify your tenant-scoped models correctly
 * implement owner scoping and prevent cross-tenant data access.
 *
 * @example
 * ```php
 * class ProductOwnerScopingTest extends TestCase
 * {
 *     use OwnerScopingContractTests;
 *
 *     protected function getModelClass(): string
 *     {
 *         return Product::class;
 *     }
 *
 *     protected function createOwner(): Model
 *     {
 *         return Store::factory()->create();
 *     }
 *
 *     protected function createModelForOwner(Model $owner): Model
 *     {
 *         return Product::factory()->forOwner($owner)->create();
 *     }
 * }
 * ```
 */
trait OwnerScopingContractTests
{
    /**
     * Get the model class being tested.
     *
     * @return class-string<Model&HasOwner>
     */
    abstract protected function getModelClass(): string;

    /**
     * Create an owner instance for testing.
     */
    abstract protected function createOwner(): Model;

    /**
     * Create a model instance for a specific owner.
     */
    abstract protected function createModelForOwner(Model $owner): Model;

    /**
     * Create a global (ownerless) model instance.
     */
    protected function createGlobalModel(): Model
    {
        $class = $this->getModelClass();

        return $class::factory()->create([
            'owner_type' => null,
            'owner_id' => null,
        ]);
    }

    public function test_model_uses_has_owner_trait(): void
    {
        $class = $this->getModelClass();

        expect(class_uses_recursive($class))->toContain(HasOwner::class);
    }

    public function test_model_can_be_assigned_owner(): void
    {
        $owner = $this->createOwner();
        $model = $this->createModelForOwner($owner);

        expect($model->hasOwner())->toBeTrue()
            ->and($model->belongsToOwner($owner))->toBeTrue();
    }

    public function test_model_can_be_global(): void
    {
        $model = $this->createGlobalModel();

        expect($model->isGlobal())->toBeTrue()
            ->and($model->hasOwner())->toBeFalse();
    }

    public function test_for_owner_scope_filters_by_owner(): void
    {
        $owner1 = $this->createOwner();
        $owner2 = $this->createOwner();

        $model1 = $this->createModelForOwner($owner1);
        $model2 = $this->createModelForOwner($owner2);

        $class = $this->getModelClass();

        // Query for owner1 should only return owner1's model
        $results = $class::withoutGlobalScopes()
            ->forOwner($owner1)
            ->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->getKey())->toBe($model1->getKey());
    }

    public function test_for_owner_with_include_global_includes_global_records(): void
    {
        $owner = $this->createOwner();

        $ownedModel = $this->createModelForOwner($owner);
        $globalModel = $this->createGlobalModel();

        $class = $this->getModelClass();

        $results = $class::withoutGlobalScopes()
            ->forOwner($owner, includeGlobal: true)
            ->get();

        expect($results)->toHaveCount(2)
            ->and($results->pluck($ownedModel->getKeyName())->toArray())
            ->toContain($ownedModel->getKey())
            ->toContain($globalModel->getKey());
    }

    public function test_global_only_scope_returns_only_global_records(): void
    {
        $owner = $this->createOwner();

        $this->createModelForOwner($owner);
        $globalModel = $this->createGlobalModel();

        $class = $this->getModelClass();

        $results = $class::withoutGlobalScopes()
            ->globalOnly()
            ->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->getKey())->toBe($globalModel->getKey());
    }

    public function test_owner_context_with_owner_scopes_queries(): void
    {
        $owner1 = $this->createOwner();
        $owner2 = $this->createOwner();

        $model1 = $this->createModelForOwner($owner1);
        $this->createModelForOwner($owner2);

        $class = $this->getModelClass();

        $results = OwnerContext::withOwner($owner1, function () use ($class) {
            return $class::withoutGlobalScopes()
                ->forOwner(OwnerContext::resolve())
                ->get();
        });

        expect($results)->toHaveCount(1)
            ->and($results->first()->getKey())->toBe($model1->getKey());
    }

    public function test_assign_owner_sets_owner(): void
    {
        $owner = $this->createOwner();
        $model = $this->createGlobalModel();

        expect($model->isGlobal())->toBeTrue();

        $model->assignOwner($owner);
        $model->save();
        $model->refresh();

        expect($model->hasOwner())->toBeTrue()
            ->and($model->belongsToOwner($owner))->toBeTrue();
    }

    public function test_remove_owner_makes_model_global(): void
    {
        $owner = $this->createOwner();
        $model = $this->createModelForOwner($owner);

        expect($model->hasOwner())->toBeTrue();

        $model->removeOwner();
        $model->save();
        $model->refresh();

        expect($model->isGlobal())->toBeTrue();
    }

    public function test_cross_tenant_access_prevented(): void
    {
        $owner1 = $this->createOwner();
        $owner2 = $this->createOwner();

        $model1 = $this->createModelForOwner($owner1);

        $class = $this->getModelClass();

        // Querying with owner2 should not find owner1's model
        $results = $class::withoutGlobalScopes()
            ->forOwner($owner2)
            ->where($model1->getKeyName(), $model1->getKey())
            ->get();

        expect($results)->toBeEmpty();
    }
}
