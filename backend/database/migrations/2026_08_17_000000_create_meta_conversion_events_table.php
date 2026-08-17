<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_conversion_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name');
            $table->string('event_id');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_name', 'event_id']);
            $table->index(['order_id', 'event_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_conversion_events');
    }
};
