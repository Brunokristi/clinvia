<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_public_sites', function (Blueprint $table) {
            $table->json('faq_items')->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('branch_public_sites', function (Blueprint $table) {
            $table->dropColumn('faq_items');
        });
    }
};