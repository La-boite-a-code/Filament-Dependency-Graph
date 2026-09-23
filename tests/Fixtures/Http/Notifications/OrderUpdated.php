<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class OrderUpdated extends Notification implements ShouldQueue {}
