<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Actions\PlaceOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Mail\OrderShippedMail;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Notifications\OrderUpdated;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Repositories\OrderRepository;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Requests\StoreOrderRequest;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Requests\UpdateOrderRequest;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Customer;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;

final class OrderController
{
    /** Counts instantiations: discovery must never create a controller. */
    public static int $instances = 0;

    public function __construct(
        private readonly OrderRepository $orders,
    ) {
        self::$instances++;
    }

    public function index(): View
    {
        return view('orders.index', ['count' => Order::query()->count()]);
    }

    public function store(StoreOrderRequest $request, PlaceOrder $placeOrder): string
    {
        $placeOrder->execute();

        return 'stored';
    }

    public function show(Order $order): string
    {
        return (string) $order->getKey();
    }

    public function update(UpdateOrderRequest $request, Order $order): string
    {
        Notification::send(Customer::query()->get(), new OrderUpdated);
        Mail::to('customer@example.com')->send(new OrderShippedMail);

        return 'updated';
    }

    public function destroy(Order $order): string
    {
        $this->orders->archive($order);

        return 'archived';
    }
}
