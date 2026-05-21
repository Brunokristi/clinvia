<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address_line_1')->nullable()->after('vat_id');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city')->nullable()->after('address_line_2');
            $table->string('postal_code')->nullable()->after('city');
            $table->string('region')->nullable()->after('postal_code');
            $table->string('country')->nullable()->after('region');

            $table->dropColumn(['name', 'description']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->text('description')->nullable()->after('vat_id');

            $table->dropColumn([
                'address_line_1',
                'address_line_2',
                'city',
                'postal_code',
                'region',
                'country',
            ]);
        });
    }
};