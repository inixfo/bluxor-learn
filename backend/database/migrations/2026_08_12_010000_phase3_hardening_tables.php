<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('auditable_type')->nullable()->index();
            $table->unsignedBigInteger('auditable_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('ip_hash')->nullable();
            $table->string('user_agent_hash')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('coupon_product', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });

        Schema::create('coupon_bundle', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'bundle_id']);
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_email')->index();
            $table->timestamp('used_at');
            $table->unique(['coupon_id', 'order_id']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('validation_id')->nullable()->after('provider_reference')->index();
            $table->string('normalized_state')->nullable()->after('status')->index();
            $table->timestamp('verified_at')->nullable()->after('failed_at');
        });

        Schema::table('landing_pages', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('published_version_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('landing_page_versions', function (Blueprint $table) {
            $table->string('original_package_path')->nullable()->after('package_path');
            $table->string('public_path')->nullable()->after('original_package_path');
            $table->unsignedBigInteger('package_size_bytes')->default(0)->after('public_path');
            $table->json('validation_report')->nullable()->after('status');
        });

        Schema::table('landing_page_offers', function (Blueprint $table) {
            $table->string('offer_key')->default('single')->after('landing_page_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('landing_page_offers', function (Blueprint $table) {
            $table->dropColumn('offer_key');
        });

        Schema::table('landing_page_versions', function (Blueprint $table) {
            $table->dropColumn(['original_package_path', 'public_path', 'package_size_bytes', 'validation_report']);
        });

        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['validation_id', 'normalized_state', 'verified_at']);
        });

        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupon_bundle');
        Schema::dropIfExists('coupon_product');
        Schema::dropIfExists('audit_logs');
    }
};
