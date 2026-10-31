<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail s fakturou (nebo upomínkou) a archivním PDF v příloze. Odesílá se synchronně.
 * Od: MAIL_FROM_ADDRESS z .env, odpověď jde na e-mail z profilu firmy.
 */
class InvoiceMail extends Mailable
{
    /**
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     */
    public function __construct(
        public readonly string $mailSubject,
        public readonly string $body,
        public readonly string $senderName,
        public readonly ?string $replyToAddress,
        public readonly array $cc,
        public readonly array $bcc,
        public readonly string $pdf,
        public readonly string $pdfName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->senderName),
            cc: $this->cc,
            bcc: $this->bcc,
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress, $this->senderName)] : [],
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invoice', text: 'mail.invoice-text');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->pdfName)->withMime('application/pdf'),
        ];
    }
}
