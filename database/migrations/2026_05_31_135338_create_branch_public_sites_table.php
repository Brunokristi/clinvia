<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_public_sites', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Branch::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->boolean('is_enabled')->default(false);
            $table->string('template')->default('default');
            $table->string('custom_domain')->nullable()->unique();

            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('logo_path')->nullable();

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();

            $table->unique('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_public_sites');
    }
};