<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_assets', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['fail', 'pending', 'progress', 'success'])->default('pending');
            $table->unsignedBigInteger('resource_type_id');
            $table->enum('resource_type', ['film_thumbnail', 'film_poster', 'film_trailer', 'episode']);
            $table->enum('asset_type', ['image', 'video']);
            $table->text('asset_url');
            $table->string('storage_file_id')->nullable();
            $table->text('storage_url')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['resource_type', 'resource_type_id', 'asset_type'],
                'cloud_assets_resource_asset_unique'
            );
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_assets');
    }
};
