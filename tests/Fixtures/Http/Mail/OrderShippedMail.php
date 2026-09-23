<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;

final class OrderShippedMail extends Mailable
{
    public function content(): Content
    {
        return new Content(markdown: 'mail.order-shipped');
    }
}
