<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('cloud_assets', 'storage_file_id')) {
                $table->string('storage_file_id')->nullable()->after('asset_url');
            }

            if (! Schema::hasColumn('cloud_assets', 'attempts')) {
                $table->unsignedInteger('attempts')->default(0)->after('storage_url');
            }

            if (! Schema::hasColumn('cloud_assets', 'last_error')) {
                $table->text('last_error')->nullable()->after('attempts');
            }

            if (! Schema::hasColumn('cloud_assets', 'uploaded_at')) {
                $table->timestamp('uploaded_at')->nullable()->after('last_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cloud_assets', function (Blueprint $table) {
            $columns = collect(['storage_file_id', 'attempts', 'last_error', 'uploaded_at'])
                ->filter(fn (string $column): bool => Schema::hasColumn('cloud_assets', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
