<?php

namespace Tests\Feature;

use App\Mail\ContactInquiryMail;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StructuredSupportContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_public_uses_published_structured_records_and_admin_crud_is_protected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();

        $this->getJson('/api/v1/admin/faq-categories')->assertUnauthorized();
        $this->actingAs($customer)->getJson('/api/v1/admin/faq-categories')->assertForbidden();

        $categoryId = $this->actingAs($admin)->postJson('/api/v1/admin/faq-categories', [
            'name' => 'Licensing',
            'slug' => 'licensing',
            'sort_order' => 7,
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $itemId = $this->actingAs($admin)->postJson('/api/v1/admin/faq-items', [
            'faq_category_id' => $categoryId,
            'question' => 'Can my team use one purchase?',
            'answer' => 'Only if the product license says team use is included.',
            'sort_order' => 1,
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/faq?q=team')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'licensing')
            ->assertJsonPath('data.0.items.0.question', 'Can my team use one purchase?');

        $this->actingAs($admin)->patchJson('/api/v1/admin/faq-items/'.$itemId, ['status' => 'inactive'])->assertOk();
        $this->getJson('/api/v1/faq?q=team')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($admin)->deleteJson('/api/v1/admin/faq-categories/'.$categoryId)->assertOk();
        $this->assertDatabaseMissing('faq_categories', ['slug' => 'licensing']);
    }

    public function test_help_center_public_search_article_routing_and_admin_crud(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();

        $categoryId = $this->actingAs($admin)->postJson('/api/v1/admin/help-categories', [
            'name' => 'Troubleshooting',
            'slug' => 'troubleshooting',
            'description' => 'Detailed support articles.',
            'icon' => 'help',
            'sort_order' => 9,
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $draftId = $this->actingAs($admin)->postJson('/api/v1/admin/help-articles', [
            'help_category_id' => $categoryId,
            'title' => 'Private draft',
            'slug' => 'private-draft',
            'summary' => 'Hidden draft.',
            'content' => 'This draft should not be public.',
            'status' => 'draft',
        ])->assertCreated()->json('data.id');

        $articleId = $this->actingAs($admin)->postJson('/api/v1/admin/help-articles', [
            'help_category_id' => $categoryId,
            'title' => 'Fix a missing receipt',
            'slug' => 'fix-missing-receipt',
            'summary' => 'Find receipt details.',
            'content' => 'Search your account orders and contact support if the receipt is missing.',
            'is_featured' => true,
            'status' => 'published',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/help-center?q=receipt')
            ->assertOk()
            ->assertJsonPath('data.results.0.slug', 'fix-missing-receipt');

        $this->getJson('/api/v1/help-center/troubleshooting/fix-missing-receipt')
            ->assertOk()
            ->assertJsonPath('data.article.title', 'Fix a missing receipt');

        $this->getJson('/api/v1/help-center/troubleshooting/private-draft')->assertNotFound();

        $this->actingAs($admin)->patchJson('/api/v1/admin/help-articles/'.$draftId, ['status' => 'published'])->assertOk();
        $this->getJson('/api/v1/help-center/troubleshooting/private-draft')->assertOk();

        $this->actingAs($admin)->deleteJson('/api/v1/admin/help-articles/'.$articleId)->assertOk();
        $this->assertDatabaseMissing('help_articles', ['id' => $articleId]);
    }

    public function test_contact_settings_are_admin_only_and_public_endpoint_exposes_safe_fields(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();

        $payload = [
            'support_email' => 'support@learn.test',
            'support_phone' => '+8801000000000',
            'support_whatsapp' => '+8801000000000',
            'business_name' => 'Bluxor',
            'business_address' => 'Configured address',
            'support_availability_text' => 'Configured availability text.',
            'smtp_password' => 'must-not-be-public',
        ];

        $this->actingAs($customer)->patchJson('/api/v1/admin/settings/contact', $payload)->assertForbidden();
        $this->actingAs($admin)->patchJson('/api/v1/admin/settings/contact', $payload)->assertOk();

        $this->getJson('/api/v1/settings/contact')
            ->assertOk()
            ->assertJsonPath('data.support_email', 'support@learn.test')
            ->assertJsonPath('data.support_phone', '+8801000000000')
            ->assertJsonMissing(['smtp_password' => 'must-not-be-public']);
    }

    public function test_existing_contact_form_flow_still_creates_inquiry_and_notification(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $this->postJson('/api/v1/contact', [
            'name' => 'Support Buyer',
            'email' => 'support-buyer@example.com',
            'subject' => 'Download / Access: Cannot open file',
            'message' => 'I need help opening a purchased file.',
        ])->assertCreated();

        $this->assertDatabaseHas('contact_inquiries', ['email' => 'support-buyer@example.com']);
        $this->assertDatabaseHas('admin_notifications', ['type' => 'contact.submitted']);
        Mail::assertQueued(ContactInquiryMail::class);
    }

    public function test_cms_about_and_legal_pages_remain_public(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['about', 'terms', 'privacy', 'refund-policy'] as $slug) {
            $this->getJson('/api/v1/content-pages/'.$slug)
                ->assertOk()
                ->assertJsonPath('data.slug', $slug);
        }

        FaqCategory::firstOrFail();
        FaqItem::firstOrFail();
        HelpCategory::firstOrFail();
        HelpArticle::firstOrFail();
        $this->assertSame('Learn by Bluxor', json_decode(DB::table('settings')->where('group', 'general')->where('key', 'site_name')->value('value'), true));
    }
}
