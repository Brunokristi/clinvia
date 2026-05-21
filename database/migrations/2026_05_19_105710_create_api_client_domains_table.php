<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_client_domains', function (Blueprint $table) {

            $table->foreignId('api_client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('domain');
            $table->boolean('is_active')->default(true);

            $table->unique(['api_client_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_client_domains');
    }
};