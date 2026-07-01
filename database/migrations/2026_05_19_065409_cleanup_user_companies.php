<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('user_companies', function (Blueprint $table) {
            if (! Schema::hasColumn('user_companies', 'user_id')) {
                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('user_companies', 'company_id')) {
                $table->foreignId('company_id')
                    ->constrained()
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('user_companies', 'role')) {
                $table->string('role')->default('company_admin');
            }

            if (! Schema::hasColumn('user_companies', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            $table->unique(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::dropIfExists('user_companies');
    }
};