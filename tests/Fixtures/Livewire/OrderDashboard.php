<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire;

use Illuminate\Contracts\View\View;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Customer;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Product;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\User;
use Livewire\Component;

final class OrderDashboard extends Component
{
    public Order $order;

    public string $search = '';

    public function mount(User $viewer): void
    {
        // Route-bound model parameters are discovered through reflection.
    }

    public function refreshProducts(): void
    {
        // Customer::query() is documentation only and must not create an edge.
        Product::query();
    }

    public function render(): View
    {
        return view('livewire.order-dashboard');
    }
}
