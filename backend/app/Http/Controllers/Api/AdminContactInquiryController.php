<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactInquiryReplyMail;
use App\Models\AdminNotification;
use App\Models\ContactInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AdminContactInquiryController extends Controller
{
    private const STATUSES = ['new', 'read', 'replied', 'resolved', 'spam'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        $query = ContactInquiry::query()
            ->when($filters['q'] ?? null, function ($query, string $term) {
                $query->where(function ($nested) use ($term) {
                    $nested
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('subject', 'like', "%{$term}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest();

        return response()->json(['data' => [
            'items' => $query->paginate(20),
            'counts' => $this->counts(),
        ]]);
    }

    public function show(ContactInquiry $contactInquiry)
    {
        $this->markRead($contactInquiry);
        $this->markNotificationRead($contactInquiry);

        return response()->json(['data' => $contactInquiry->fresh()->load('replies.admin:id,name,email')]);
    }

    public function update(Request $request, ContactInquiry $contactInquiry)
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (array_key_exists('admin_notes', $data)) {
            $contactInquiry->admin_notes = $data['admin_notes'];
        }

        if (isset($data['status'])) {
            $this->applyStatus($contactInquiry, $data['status']);
        }

        $contactInquiry->save();

        return response()->json(['data' => $contactInquiry->fresh()->load('replies.admin:id,name,email')]);
    }

    public function reply(Request $request, ContactInquiry $contactInquiry)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        try {
            Mail::to($contactInquiry->email, $contactInquiry->name)
                ->send(new ContactInquiryReplyMail($contactInquiry, $request->user(), $data['subject'], $data['message']));
        } catch (\Throwable $exception) {
            Log::warning('Contact inquiry reply failed.', [
                'contact_inquiry_id' => $contactInquiry->id,
                'admin_user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Reply could not be sent. Check mail configuration and try again.'], 502);
        }

        $contactInquiry->replies()->create([
            'admin_user_id' => $request->user()->id,
            'sent_to' => $contactInquiry->email,
            'subject' => $data['subject'],
            'message' => $data['message'],
        ]);

        $contactInquiry->forceFill([
            'status' => 'replied',
            'read_at' => $contactInquiry->read_at ?: now(),
            'replied_at' => now(),
        ])->save();

        $this->markNotificationRead($contactInquiry);

        return response()->json(['data' => $contactInquiry->fresh()->load('replies.admin:id,name,email')]);
    }

    private function counts(): array
    {
        $statusCounts = ContactInquiry::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'all' => ContactInquiry::count(),
            'new' => (int) ($statusCounts['new'] ?? 0),
            'read' => (int) ($statusCounts['read'] ?? 0),
            'replied' => (int) ($statusCounts['replied'] ?? 0),
            'resolved' => (int) ($statusCounts['resolved'] ?? 0),
            'spam' => (int) ($statusCounts['spam'] ?? 0),
        ];
    }

    private function applyStatus(ContactInquiry $inquiry, string $status): void
    {
        $inquiry->status = $status;

        if ($status === 'new') {
            $inquiry->read_at = null;
            return;
        }

        if (! $inquiry->read_at) {
            $inquiry->read_at = now();
        }

        if ($status === 'resolved' && ! $inquiry->resolved_at) {
            $inquiry->resolved_at = now();
        }

        if ($status === 'replied' && ! $inquiry->replied_at) {
            $inquiry->replied_at = now();
        }
    }

    private function markRead(ContactInquiry $inquiry): void
    {
        if ($inquiry->status === 'new') {
            $inquiry->forceFill([
                'status' => 'read',
                'read_at' => now(),
            ])->save();
        } elseif (! $inquiry->read_at) {
            $inquiry->forceFill(['read_at' => now()])->save();
        }
    }

    private function markNotificationRead(ContactInquiry $inquiry): void
    {
        AdminNotification::where('entity_type', ContactInquiry::class)
            ->where('entity_id', $inquiry->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
