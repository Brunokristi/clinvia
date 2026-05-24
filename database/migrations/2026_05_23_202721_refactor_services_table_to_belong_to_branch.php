<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop pivot and prices tables
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('branch_services');

        // 2. Refactor services table
        Schema::table('services', function (Blueprint $table) {
            // Drop old unique constraint
            $table->dropUnique(['company_id', 'slug']);

            // Make company_id nullable
            $table->foreignId('company_id')->nullable()->change();

            // Add branch_id constrained to branches
            $table->foreignId('branch_id')
                ->after('company_id')
                ->constrained()
                ->cascadeOnDelete();

            // Add price columns
            $table->decimal('insurance_amount', 10, 2)->nullable()->after('duration_minutes');
            $table->string('insurance_note')->nullable()->after('insurance_amount');
            $table->decimal('self_pay_amount', 10, 2)->nullable()->after('insurance_note');
            $table->string('self_pay_note')->nullable()->after('self_pay_amount');

            // Unique constraint on branch_id and slug
            $table->unique(['branch_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'slug']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'insurance_amount',
                'insurance_note',
                'self_pay_amount',
                'self_pay_note',
            ]);
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('branch_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('custom_title')->nullable();
            $table->text('custom_description')->nullable();
            $table->boolean('is_available')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_service_id')->constrained('branch_services')->cascadeOnDelete();
            $table->string('price_type');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency')->default('EUR');
            $table->string('note')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
