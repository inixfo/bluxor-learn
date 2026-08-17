<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('community_enabled')->default(false)->after('featured');
            $table->string('community_name')->nullable()->after('community_enabled');
            $table->string('community_url', 2048)->nullable()->after('community_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['community_enabled', 'community_name', 'community_url']);
        });
    }
};
