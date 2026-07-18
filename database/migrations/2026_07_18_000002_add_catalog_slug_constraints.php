<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['types', 'statuses', 'genres', 'countries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->unique('slug', $tableName . '_slug_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (['types', 'statuses', 'genres', 'countries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique($tableName . '_slug_unique');
            });
        }
    }
};
