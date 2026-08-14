<?php

declare(strict_types=1);

namespace App\Modules\Settings\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Deliberately view-less: the point of this message is to exercise the
 * transport, so it must not be able to fail for a template reason.
 */
class TestMail extends Mailable
{
    public function __construct(
        public readonly string $appName,
        public readonly string $sentBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — mail configuration test', ['app' => $this->appName]),
        );
    }

    public function content(): Content
    {
        $body = __('This is a test message sent from :app by :user. If you are reading it, the configured mail transport works.', [
            'app' => e($this->appName),
            'user' => e($this->sentBy),
        ]);

        return new Content(htmlString: "<p>{$body}</p>");
    }
}
