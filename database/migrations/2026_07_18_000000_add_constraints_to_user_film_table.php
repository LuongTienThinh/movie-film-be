<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_film', function (Blueprint $table) {
            $table->unique(['user_id', 'film_id'], 'user_film_user_film_unique');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('film_id')->references('id')->on('films')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_film', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['film_id']);
            $table->dropUnique('user_film_user_film_unique');
        });
    }
};
