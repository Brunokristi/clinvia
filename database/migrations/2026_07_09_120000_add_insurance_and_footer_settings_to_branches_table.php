<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->json('contracted_insurance_companies')->nullable()->after('notification_settings');
            $table->boolean('show_other_branches_in_footer')->default(false)->after('contracted_insurance_companies');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['contracted_insurance_companies', 'show_other_branches_in_footer']);
        });
    }
};
