<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->boolean('is_for_someone_else')->default(false)->after('last_name');
            $table->string('requester_name')->nullable()->after('is_for_someone_else');
            $table->string('requester_email')->nullable()->after('requester_name');
            $table->string('requester_phone')->nullable()->after('requester_email');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'is_for_someone_else',
                'requester_name',
                'requester_email',
                'requester_phone',
            ]);
        });
    }
};
