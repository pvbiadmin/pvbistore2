<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use JetBrains\PhpStorm\Pure;

class MayaPayment extends Mailable
{
    use Queueable, SerializesModels;

    public $subject, $contactMessage, $email;

    /**
     * Create a new message instance.
     */
    public function __construct($subject, $contactMessage, $email)
    {
        $this->subject = $subject;
        $this->contactMessage = $contactMessage;
        $this->email = $email;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->email,
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    #[Pure] public function content(): Content
    {
        return new Content(
            view: 'Mail.Maya-Payment',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
