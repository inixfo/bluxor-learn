<?php

namespace Tests\Feature;

use App\Mail\ContactInquiryMail;
use App\Mail\ContactInquiryReplyMail;
use App\Models\AdminNotification;
use App\Models\ContactInquiry;
use App\Models\ContactInquiryReply;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminContactInquiryInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_submission_stores_inquiry_notification_validates_and_rate_limits(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $this->postJson('/api/v1/contact', [
            'name' => 'Buyer',
            'email' => 'buyer@example.com',
            'subject' => 'Download issue',
            'message' => 'I cannot access the product I purchased.',
        ])->assertCreated()
            ->assertJsonPath('data.message', 'Thanks for contacting us. Your message has been received.');

        $inquiry = ContactInquiry::where('email', 'buyer@example.com')->firstOrFail();
        $this->assertSame('new', $inquiry->status);
        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'contact.submitted',
            'entity_type' => ContactInquiry::class,
            'entity_id' => $inquiry->id,
            'url' => '/admin/contact-inquiries/'.$inquiry->id,
        ]);
        Mail::assertQueued(ContactInquiryMail::class);

        $this->postJson('/api/v1/contact', [
            'name' => '',
            'email' => 'not-an-email',
            'subject' => '',
            'message' => 'short',
        ])->assertUnprocessable();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/contact', [
                'name' => 'Rate Buyer',
                'email' => 'rate'.$i.'@example.com',
                'subject' => 'Question '.$i,
                'message' => 'This is a valid rate limit test message.',
            ]);
        }

        $this->postJson('/api/v1/contact', [
            'name' => 'Rate Buyer',
            'email' => 'rate-final@example.com',
            'subject' => 'Question final',
            'message' => 'This is a valid rate limit test message.',
        ])->assertStatus(429);
    }

    public function test_admin_can_list_inquiries_and_customer_or_guest_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();

        ContactInquiry::create([
            'uuid' => fake()->uuid(),
            'name' => 'Newest Buyer',
            'email' => 'newest@example.com',
            'subject' => 'Latest question',
            'message' => 'Please help with my purchase.',
            'status' => 'new',
        ]);
        ContactInquiry::create([
            'uuid' => fake()->uuid(),
            'name' => 'Old Buyer',
            'email' => 'old@example.com',
            'subject' => 'Old question',
            'message' => 'Older message.',
            'status' => 'resolved',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/admin/contact-inquiries')->assertUnauthorized();
        $this->actingAs($customer)->getJson('/api/v1/admin/contact-inquiries')->assertForbidden();

        $this->actingAs($admin)->getJson('/api/v1/admin/contact-inquiries?q=newest&status=new')
            ->assertOk()
            ->assertJsonPath('data.items.data.0.email', 'newest@example.com')
            ->assertJsonPath('data.counts.all', 2)
            ->assertJsonPath('data.counts.new', 1)
            ->assertJsonPath('data.counts.resolved', 1);
    }

    public function test_admin_can_view_inquiry_and_it_marks_inquiry_and_notification_read(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $inquiry = $this->inquiry();
        $notification = AdminNotification::create([
            'uuid' => fake()->uuid(),
            'type' => 'contact.submitted',
            'title' => 'New contact inquiry',
            'message' => 'Help from Buyer',
            'url' => '/admin/contact-inquiries/'.$inquiry->id,
            'entity_type' => ContactInquiry::class,
            'entity_id' => $inquiry->id,
        ]);

        $this->actingAs($admin)->getJson('/api/v1/admin/contact-inquiries/'.$inquiry->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'read')
            ->assertJsonPath('data.email', 'buyer@example.com');

        $this->assertNotNull($inquiry->fresh()->read_at);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_admin_can_update_inquiry_statuses_and_notes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $inquiry = $this->inquiry();

        $this->actingAs($admin)->patchJson('/api/v1/admin/contact-inquiries/'.$inquiry->id, [
            'status' => 'resolved',
            'admin_notes' => 'Access link resent.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.admin_notes', 'Access link resent.');

        $this->assertNotNull($inquiry->fresh()->resolved_at);

        $this->actingAs($admin)->patchJson('/api/v1/admin/contact-inquiries/'.$inquiry->id, [
            'status' => 'spam',
        ])->assertOk()->assertJsonPath('data.status', 'spam');

        $this->actingAs($admin)->patchJson('/api/v1/admin/contact-inquiries/'.$inquiry->id, [
            'status' => 'new',
        ])->assertOk()->assertJsonPath('data.status', 'new');

        $this->assertNull($inquiry->fresh()->read_at);
    }

    public function test_admin_can_reply_and_reply_history_is_stored(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $inquiry = $this->inquiry();

        $this->actingAs($admin)->postJson('/api/v1/admin/contact-inquiries/'.$inquiry->id.'/reply', [
            'subject' => 'Re: Access problem',
            'message' => 'Thanks for contacting us. Please try your library again.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'replied')
            ->assertJsonPath('data.replies.0.sent_to', 'buyer@example.com')
            ->assertJsonPath('data.replies.0.admin_user_id', $admin->id);

        Mail::assertSent(ContactInquiryReplyMail::class);
        $this->assertSame(1, ContactInquiryReply::where('contact_inquiry_id', $inquiry->id)->count());
        $this->assertNotNull($inquiry->fresh()->replied_at);
    }

    public function test_failed_reply_does_not_mark_replied_or_store_history(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $inquiry = $this->inquiry();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

        $this->actingAs($admin)->postJson('/api/v1/admin/contact-inquiries/'.$inquiry->id.'/reply', [
            'subject' => 'Re: Access problem',
            'message' => 'This should fail to send.',
        ])->assertStatus(502);

        $this->assertSame('new', $inquiry->fresh()->status);
        $this->assertSame(0, ContactInquiryReply::where('contact_inquiry_id', $inquiry->id)->count());
    }

    private function inquiry(): ContactInquiry
    {
        return ContactInquiry::create([
            'uuid' => fake()->uuid(),
            'name' => 'Buyer',
            'email' => 'buyer@example.com',
            'subject' => 'Access problem',
            'message' => 'I cannot access my download.',
            'status' => 'new',
        ]);
    }
}
