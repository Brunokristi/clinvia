<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_steps', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->integer('number')->nullable();
            $table->string('title')->nullable();
            $table->text('text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_steps');
    }
};