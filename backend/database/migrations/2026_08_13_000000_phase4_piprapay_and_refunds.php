<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway');
            $table->string('idempotency_key')->unique();
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_refund_id')->nullable()->index();
            $table->string('refund_type')->default('full');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('BDT');
            $table->string('status')->default('processing')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });

        Schema::table('entitlements', function (Blueprint $table) {
            $table->string('revocation_reason')->nullable()->after('revoked_at');
            $table->string('revocation_reference')->nullable()->after('revocation_reason');
        });

        Schema::table('landing_page_offers', function (Blueprint $table) {
            $table->unique(['landing_page_id', 'offer_key']);
        });
    }

    public function down(): void
    {
        Schema::table('landing_page_offers', function (Blueprint $table) {
            $table->dropUnique(['landing_page_id', 'offer_key']);
        });

        Schema::table('entitlements', function (Blueprint $table) {
            $table->dropColumn(['revocation_reason', 'revocation_reference']);
        });

        Schema::dropIfExists('refund_attempts');
    }
};
