<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderUpdated extends Notification implements ShouldQueue
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->view('mail.order-updated');
    }
}
