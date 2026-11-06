<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) (config('settings.repositories.database.table') ?? 'settings');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->{commerce_json_column_type('commerce-support', 'jsonb')}('payload');
            $table->timestamps();
            $table->unique(['group', 'name']);
        });
    }
};
