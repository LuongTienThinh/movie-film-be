<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('google_id', 'users_google_id_provider_unique');
            $table->unique('facebook_id', 'users_facebook_id_provider_unique');
        });

        Schema::table('user_meta', function (Blueprint $table) {
            $table->unique(['user_id', 'meta_key'], 'user_meta_user_key_unique');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_meta', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('user_meta_user_key_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_google_id_provider_unique');
            $table->dropUnique('users_facebook_id_provider_unique');
        });
    }
};
