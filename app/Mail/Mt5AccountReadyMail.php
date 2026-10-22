<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Mt5AccountReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $login,
        public string $password,
        public string $server,
        public string $planName = 'Standard',
        public string $currency = 'USDT'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kryvex Exchange - Your MetaTrader 5 Account'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mt5-account-ready'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
