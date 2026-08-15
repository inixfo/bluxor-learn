<?php

namespace Tests\Feature;

use App\Mail\ContactInquiryMail;
use App\Models\ContentPage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContentPagesProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_public_company_support_and_legal_pages_are_seeded_and_published(): void
    {
        $this->seed(DatabaseSeeder::class);

        $expected = [
            'about' => 'About Learn by Bluxor',
            'contact' => 'Contact Learn by Bluxor',
            'help' => 'Help Center',
            'faq' => 'Frequently Asked Questions',
            'download-help' => 'Download Help',
            'terms' => 'Terms of Use',
            'privacy' => 'Privacy Policy',
            'refund-policy' => 'Refund Policy',
        ];

        $this->assertSame(8, ContentPage::whereIn('slug', array_keys($expected))->count());

        foreach ($expected as $slug => $title) {
            $this->getJson('/api/v1/content-pages/'.$slug)
                ->assertOk()
                ->assertJsonPath('data.slug', $slug)
                ->assertJsonPath('data.title', $title)
                ->assertJsonPath('data.status', 'published')
                ->assertJsonStructure(['data' => ['content', 'meta_title', 'meta_description']]);
        }
    }

    public function test_unpublished_content_page_is_not_public_but_admin_can_update_it(): void
    {
        $this->seed(DatabaseSeeder::class);

        $page = ContentPage::where('slug', 'faq')->firstOrFail();
        $page->update(['status' => 'draft']);

        $this->getJson('/api/v1/content-pages/faq')->assertNotFound();

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $this->actingAs($admin)->patchJson('/api/v1/admin/content-pages/'.$page->id, [
            'status' => 'published',
            'meta_title' => 'Custom FAQ Meta',
            'content' => '# Custom FAQ',
        ])->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.meta_title', 'Custom FAQ Meta')
            ->assertJsonPath('data.content', '# Custom FAQ');
    }

    public function test_contact_form_uses_existing_backend_and_success_message(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $this->postJson('/api/v1/contact', [
            'name' => 'Buyer',
            'email' => 'buyer@example.com',
            'subject' => 'Download question',
            'message' => 'I need help accessing a purchased product.',
        ])->assertCreated()
            ->assertJsonPath('data.message', 'Thanks for contacting us. Your message has been received.');

        Mail::assertQueued(ContactInquiryMail::class);
    }
}
