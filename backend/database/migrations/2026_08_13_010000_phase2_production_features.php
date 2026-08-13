<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('contact_inquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->index();
            $table->string('subject');
            $table->text('message');
            $table->string('status')->default('new')->index();
            $table->string('ip_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_user_id');
            $table->string('provider_email')->nullable()->index();
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });

        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type')->index();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('url')->nullable();
            $table->string('entity_type')->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        $now = now();
        $pages = [
            ['About Bluxor', 'about', 'Learn by Bluxor publishes practical digital learning resources for learners who want usable skills, not passive content.'],
            ['Contact', 'contact', 'Use the contact form on this page for support, order access, payment, or download questions.'],
            ['Help', 'help', 'After a verified payment, purchases appear in your account library. Guest buyers receive secure access by email and may create an account later using the same email.'],
            ['FAQ', 'faq', "Products are delivered as protected digital files. Prices and discounts are calculated by the backend at checkout.\n\nIf a payment is pending, wait a few minutes and refresh your receipt page."],
            ['Download Help', 'download-help', 'Downloads are available after verified payment. Sign in to your library, or use the secure guest access link sent after checkout.'],
            ['Terms', 'terms', 'Draft legal terms. The site owner should review and replace this content before public launch.'],
            ['Privacy Policy', 'privacy', 'Draft privacy policy. The site owner should review and replace this content before public launch.'],
            ['Refund Policy', 'refund-policy', 'Draft refund policy. The site owner should review and replace this content before public launch.'],
        ];

        foreach ($pages as [$title, $slug, $content]) {
            DB::table('content_pages')->insert([
                'uuid' => (string) Str::uuid(),
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'meta_title' => $title,
                'meta_description' => Str::limit($content, 155),
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('contact_inquiries');
        Schema::dropIfExists('content_pages');
    }
};
