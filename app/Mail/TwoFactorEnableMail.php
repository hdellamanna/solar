<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 2FA-enrollment confirmation email (Auth Phase 3).
 *
 * Sent when the user clicks "Enable 2FA" in Settings. The
 * `confirmUrl` is a `temporarySignedRoute` minted by the
 * service layer; we receive the final URL as a constructor
 * argument and do not re-sign or re-time it here.
 *
 * Subject is ASCII-only (no accents) per the design-doc gotcha
 * — some clients mangle non-ASCII subjects.
 */
class TwoFactorEnableMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $confirmUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ativar verificacao em duas etapas - Solar Money',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.two-factor-enable',
        );
    }
}
