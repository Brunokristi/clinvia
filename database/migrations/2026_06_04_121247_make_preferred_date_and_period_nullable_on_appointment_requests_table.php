<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->date('preferred_date')->nullable()->change();
            $table->string('preferred_period')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->date('preferred_date')->nullable(false)->change();
            $table->string('preferred_period')->nullable(false)->change();
        });
    }
};