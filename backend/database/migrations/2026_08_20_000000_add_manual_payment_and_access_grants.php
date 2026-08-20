<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('payment_method', 40);
            $table->string('reference')->nullable();
            $table->text('reason');
            $table->timestamp('approved_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('entitlements', function (Blueprint $table) {
            $table->string('grant_source', 40)->default('purchase')->after('customer_email');
            $table->foreignId('granted_by_user_id')->nullable()->after('grant_source')->constrained('users')->nullOnDelete();
            $table->text('grant_note')->nullable()->after('granted_by_user_id');
            $table->foreignId('revoked_by_user_id')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
        });
        DB::table('entitlements')->whereNull('grant_source')->update(['grant_source' => 'purchase']);

        Schema::table('entitlements', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
            $table->foreignId('order_item_id')->nullable()->change();
        });

        Schema::table('download_events', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
        });

        Schema::create('resource_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default('active')->index();
            $table->foreignId('granted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_access_grants');

        Schema::table('download_events', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable(false)->change();
        });

        Schema::table('entitlements', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable(false)->change();
            $table->foreignId('order_item_id')->nullable(false)->change();
        });

        Schema::table('entitlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by_user_id');
            $table->dropColumn(['grant_source', 'grant_note']);
            $table->dropConstrainedForeignId('granted_by_user_id');
        });

        Schema::dropIfExists('manual_payment_approvals');
    }
};
