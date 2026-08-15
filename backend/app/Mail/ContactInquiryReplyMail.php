<?php

namespace App\Mail;

use App\Models\ContactInquiry;
use App\Models\User;
use Illuminate\Mail\Mailable;

class ContactInquiryReplyMail extends Mailable
{
    public function __construct(
        public readonly ContactInquiry $inquiry,
        public readonly User $admin,
        public readonly string $replySubject,
        public readonly string $replyMessage,
    ) {}

    public function build(): self
    {
        return $this
            ->replyTo(config('mail.from.address'), config('mail.from.name'))
            ->subject($this->replySubject)
            ->html('
                <h1>'.e($this->replySubject).'</h1>
                <p>'.nl2br(e($this->replyMessage)).'</p>
                <hr>
                <p style="color:#64748b;font-size:12px">
                    This reply was sent by Learn by Bluxor support regarding your inquiry:
                    '.e($this->inquiry->subject).'
                </p>
            ');
    }
}
