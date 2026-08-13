<?php

namespace App\Mail;

use App\Models\ContactInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactInquiryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ContactInquiry $inquiry) {}

    public function build(): self
    {
        return $this
            ->replyTo($this->inquiry->email, $this->inquiry->name)
            ->subject('New Learn by Bluxor contact: '.$this->inquiry->subject)
            ->html('
                <h1>New contact inquiry</h1>
                <p><strong>Name:</strong> '.e($this->inquiry->name).'</p>
                <p><strong>Email:</strong> '.e($this->inquiry->email).'</p>
                <p><strong>Subject:</strong> '.e($this->inquiry->subject).'</p>
                <p>'.nl2br(e($this->inquiry->message)).'</p>
            ');
    }
}
