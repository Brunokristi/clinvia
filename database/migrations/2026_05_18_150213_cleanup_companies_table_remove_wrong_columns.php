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

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'user_id')) {
                try {
                    $table->dropForeign(['user_id']);
                } catch (Throwable $e) {
                    //
                }

                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('companies', 'company_id')) {
                try {
                    $table->dropForeign(['company_id']);
                } catch (Throwable $e) {
                    //
                }

                $table->dropColumn('company_id');
            }

            if (Schema::hasColumn('companies', 'role')) {
                $table->dropColumn('role');
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('companies', 'company_id')) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            }

            if (! Schema::hasColumn('companies', 'role')) {
                $table->string('role')->nullable();
            }
        });
    }
};