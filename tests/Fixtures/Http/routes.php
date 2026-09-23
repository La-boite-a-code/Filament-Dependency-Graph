<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\ShowDashboardController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire\OrderDashboard;

/** @var Router $router */
$router->middleware('web')->group(static function (Router $router): void {
    $router->get('orders', [OrderController::class, 'index'])->name('orders.index');
    $router->post('orders', [OrderController::class, 'store'])->middleware('auth')->name('orders.store');
    $router->get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    $router->put('orders/{order}', [OrderController::class, 'update'])->middleware('auth')->name('orders.update');
    $router->delete('orders/{order}', [OrderController::class, 'destroy'])
        ->middleware(['auth', 'verified'])
        ->name('orders.destroy');
    $router->get('dashboard', ShowDashboardController::class)->name('dashboard');
    $router->get('live/orders', OrderDashboard::class)->name('live.orders');
    $router->view('about', 'pages.about')->name('about');
    $router->redirect('home', '/dashboard');
    $router->get('health', static fn (): string => 'ok')->name('health');
    $router->get('internal/debug', static fn (): string => 'debug')->name('debug.internal');
});
