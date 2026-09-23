<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Listeners;

use Illuminate\Support\Facades\Mail;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderPlaced;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Mail\OrderShippedMail;

final class SendOrderConfirmation
{
    public function handle(OrderPlaced $event): void
    {
        Mail::to('customer@example.com')->queue(new OrderShippedMail);
    }
}
