<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Password-reset email sent when the user requests the forgot-password
 * flow. The raw token is embedded in a `temporarySignedRoute` URL
 * minted by the service layer; we receive the final URL as a
 * constructor argument and do not re-sign or re-time it here.
 *
 * Subject is ASCII-only (no accents) per the design-doc gotcha —
 * some clients mangle non-ASCII subjects.
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Redefina sua senha - Solar Money',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            text: 'emails.password-reset-text',
        );
    }
}
