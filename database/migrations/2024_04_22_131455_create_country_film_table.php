<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('country_film', function (Blueprint $table) {
            $table->unsignedBigInteger('film_id');
            $table->unsignedBigInteger('country_id');
            $table->timestamps();

            $table->foreign('film_id')->references('id')->on('films');
            $table->foreign('country_id')->references('id')->on('countries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_film');
    }
};
