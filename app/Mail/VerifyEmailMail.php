<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Verification email sent on registration and on explicit re-send.
 *
 * The raw token is the only thing that ever proves the user controls
 * the destination inbox, so we embed it in the URL ourselves. The
 * URL is generated as a `temporarySignedRoute` by the caller (see
 * {@see EmailVerificationService::sendVerificationEmail()})
 * — we do not re-sign or re-time it here.
 */
class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $verificationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirme seu email - Solar Money',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email',
            text: 'emails.verify-email-text',
        );
    }
}
