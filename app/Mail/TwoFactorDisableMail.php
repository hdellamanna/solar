<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 2FA-disable confirmation email (Auth Phase 3).
 *
 * Sent when the user enters their password and clicks
 * "Disable 2FA" in Settings. The `confirmUrl` is a
 * `temporarySignedRoute` minted by the service layer.
 *
 * Subject is ASCII-only per the design-doc gotcha.
 */
class TwoFactorDisableMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $confirmUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Desativar verificacao em duas etapas - Solar Money',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.two-factor-disable',
        );
    }
}
