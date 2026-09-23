<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers;

use Illuminate\Notifications\DatabaseNotification;

final class NotificationController
{
    public function index(): string
    {
        return (string) DatabaseNotification::query()->count();
    }
}
