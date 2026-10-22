<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $action, // 'approved' or 'rejected'
        public string $userName,
        public string $userEmail,
        public ?string $clientId = null,
        public ?string $reason = null,
        public ?string $processedAt = null
    ) {
        $this->processedAt = $processedAt ?: now()->format('M d, Y h:i A T');
    }

    public function envelope(): Envelope
    {
        $subject = $this->action === 'approved'
            ? 'Account Deletion Request Approved - Kryvex'
            : 'Account Deletion Request Update - Kryvex';

        return new Envelope(
            subject: $subject
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-deletion-status'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
