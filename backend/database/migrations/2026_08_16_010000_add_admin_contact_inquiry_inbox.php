<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_inquiries', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('status');
            $table->timestamp('replied_at')->nullable()->after('read_at');
            $table->timestamp('resolved_at')->nullable()->after('replied_at');
            $table->text('admin_notes')->nullable()->after('resolved_at');
        });

        Schema::create('contact_inquiry_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sent_to');
            $table->string('subject');
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiry_replies');

        Schema::table('contact_inquiries', function (Blueprint $table) {
            $table->dropColumn(['read_at', 'replied_at', 'resolved_at', 'admin_notes']);
        });
    }
};
