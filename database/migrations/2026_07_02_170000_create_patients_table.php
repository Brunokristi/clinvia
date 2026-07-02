<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Branch::class)->constrained()->cascadeOnDelete();
            $table->string('patient_name');
            $table->string('patient_email')->nullable();
            $table->string('patient_phone')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'patient_name']);
            $table->index(['branch_id', 'patient_email']);
            $table->index(['branch_id', 'patient_phone']);
            $table->index(['branch_id', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
