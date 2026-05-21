<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_tags', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tags');
    }
};