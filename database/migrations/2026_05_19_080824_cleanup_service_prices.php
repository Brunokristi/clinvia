<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_prices', function (Blueprint $table) {
            $table->foreignId('branch_service_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('price_type');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency')->default('EUR');
            $table->text('note')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->integer('sort_order')->default(0);

            $table->unique(['branch_service_id', 'price_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};