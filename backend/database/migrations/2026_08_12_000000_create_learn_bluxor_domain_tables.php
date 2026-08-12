<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('status')->default('active')->after('password');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('product_type')->default('ebook')->index();
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('regular_price_minor');
            $table->unsignedBigInteger('sale_price_minor')->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->string('cover_image_path')->nullable();
            $table->string('featured_image_path')->nullable();
            $table->boolean('featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_tag', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('file_type');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('storage_disk')->default('private');
            $table->string('storage_path');
            $table->string('version')->default('1.0.0');
            $table->unsignedInteger('download_limit')->nullable();
            $table->unsignedInteger('download_expiration_days')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('cover_image_path')->nullable();
            $table->unsignedBigInteger('regular_value_minor');
            $table->unsignedBigInteger('bundle_price_minor');
            $table->unsignedBigInteger('sale_price_minor')->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bundle_product', function (Blueprint $table) {
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['bundle_id', 'product_id']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->unsignedInteger('percentage_bps')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedBigInteger('minimum_order_minor')->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->index();
            $table->string('customer_phone')->nullable();
            $table->string('order_status')->default('pending')->index();
            $table->string('payment_status')->default('pending')->index();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3)->default('BDT');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('landing_page_version_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('purchasable_type');
            $table->unsignedBigInteger('purchasable_id');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bundle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_slug');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3)->default('BDT');
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('BDT');
            $table->string('status')->default('initiated')->index();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('event_key');
            $table->string('provider_transaction_id')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('payload_hash');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'event_key']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('customer_email')->index();
            $table->string('status')->default('active')->index();
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'product_id']);
        });

        Schema::create('download_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_file_id')->constrained()->cascadeOnDelete();
            $table->string('customer_email')->index();
            $table->string('ip_hash')->nullable();
            $table->string('user_agent_hash')->nullable();
            $table->timestamp('downloaded_at');
        });

        Schema::create('guest_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('email')->index();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('draft')->index();
            $table->foreignId('primary_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('landing_page_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('package_path');
            $table->json('manifest');
            $table->string('entry_path');
            $table->string('checksum');
            $table->string('sdk_version')->default('2');
            $table->string('status')->default('validated')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['landing_page_id', 'version_number']);
        });

        Schema::create('landing_page_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landing_page_version_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('offer_type');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bundle_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('landing_page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('landing_page_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bundle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_key_hash')->nullable()->index();
            $table->string('session_key_hash')->nullable()->index();
            $table->string('event_name')->index();
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('key');
            $table->json('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->timestamps();
            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        foreach ([
            'settings', 'analytics_events', 'landing_page_offers', 'landing_page_versions', 'landing_pages',
            'guest_access_tokens', 'download_events', 'entitlements', 'order_status_histories', 'payment_events',
            'payment_transactions', 'order_items', 'orders', 'coupons', 'bundle_product', 'bundles',
            'product_files', 'product_tag', 'products', 'tags', 'categories', 'role_user', 'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'status', 'last_login_at']);
        });
    }
};
