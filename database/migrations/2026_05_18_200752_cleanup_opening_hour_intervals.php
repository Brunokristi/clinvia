<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opening_hour_intervals', function (Blueprint $table) {
            $table->foreignId('opening_hour_id')->constrained()->cascadeOnDelete();
            $table->time('opens_at');
            $table->time('closes_at');
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_hour_intervals');
    }
};