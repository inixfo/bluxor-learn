<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminDiagnosticMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function build(): self
    {
        return $this
            ->subject('Learn by Bluxor SMTP test')
            ->html('<h1>SMTP test successful</h1><p>Learn by Bluxor sent this diagnostic email using the configured mailer.</p>');
    }
}
