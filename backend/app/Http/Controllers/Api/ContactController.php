<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactInquiryMail;
use App\Models\ContactInquiry;
use App\Services\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function submit(Request $request, AdminNotificationService $notifications)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $inquiry = ContactInquiry::create($data + [
            'uuid' => (string) Str::uuid(),
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip()) : null,
        ]);

        $notifications->create(
            'contact.submitted',
            'New contact inquiry',
            $inquiry->subject.' from '.$inquiry->name,
            '/admin/contact-inquiries/'.$inquiry->id,
            $inquiry
        );

        try {
            Mail::to(config('mail.admin_address', config('mail.from.address')))->queue(new ContactInquiryMail($inquiry));
        } catch (\Throwable $exception) {
            Log::warning('Contact inquiry notification email failed.', [
                'inquiry_id' => $inquiry->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json(['data' => ['message' => 'Thanks for contacting us. Your message has been received.']], 201);
    }
}
