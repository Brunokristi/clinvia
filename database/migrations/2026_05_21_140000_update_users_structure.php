<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [''];

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'first_name' => $parts[0] !== '' ? $parts[0] : null,
                        'last_name' => isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null,
                    ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $name = trim(implode(' ', array_filter([$user->first_name, $user->last_name])));

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'name' => $name !== '' ? $name : null,
                    ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};