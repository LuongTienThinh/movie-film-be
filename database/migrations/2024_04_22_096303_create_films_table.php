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
        Schema::create('films', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('origin_name');
            $table->string('slug');
            $table->text('description');
            $table->string('quality');
            $table->string('poster_url');
            $table->string('thumbnail_url');
            $table->string('trailer_url');
            $table->string('time');
            $table->integer('episode_current');
            $table->integer('episode_total');
            $table->integer('year');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('status_id');
            $table->boolean('is_delete')->default(false);
            $table->timestamps();

            $table->foreign('type_id')->references('id')->on('types');
            $table->foreign('status_id')->references('id')->on('statuses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('films');
    }
};