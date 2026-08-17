<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('current_url', 2048)->nullable()->after('properties');
            $table->string('path', 512)->nullable()->after('current_url');
            $table->string('referrer', 2048)->nullable()->after('path');
            $table->string('referrer_host')->nullable()->after('referrer');
            $table->string('source')->nullable()->index()->after('referrer_host');
            $table->string('medium')->nullable()->index()->after('source');
            $table->string('campaign')->nullable()->index()->after('medium');
            $table->string('content')->nullable()->after('campaign');
            $table->string('term')->nullable()->after('content');
            $table->string('fbclid', 512)->nullable()->after('term');
            $table->string('gclid', 512)->nullable()->after('fbclid');
            $table->string('msclkid', 512)->nullable()->after('gclid');
            $table->string('ttclid', 512)->nullable()->after('msclkid');
            $table->json('attribution')->nullable()->after('ttclid');

            $table->index(['landing_page_id', 'occurred_at']);
            $table->index(['landing_page_id', 'source', 'medium']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex(['landing_page_id', 'occurred_at']);
            $table->dropIndex(['landing_page_id', 'source', 'medium']);
            $table->dropColumn([
                'current_url', 'path', 'referrer', 'referrer_host', 'source', 'medium',
                'campaign', 'content', 'term', 'fbclid', 'gclid', 'msclkid', 'ttclid', 'attribution',
            ]);
        });
    }
};
