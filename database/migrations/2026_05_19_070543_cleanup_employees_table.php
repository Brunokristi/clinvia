<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('slug');
            $table->string('title_before')->nullable();
            $table->string('title_after')->nullable();
            $table->string('position')->nullable();
            $table->text('bio')->nullable();

            $table->string('photo_path')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};