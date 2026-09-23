@extends('layouts.app')

@section('content')
    {{-- @include('commented.out') must be ignored --}}
    <x-alert type="info" />
    <x-filament::badge>{{ $count }}</x-filament::badge>

    @foreach ($orders as $order)
        @include('orders.partials.row', ['order' => $order])
    @endforeach

    @each('orders.partials.row', $orders, 'order', 'orders.partials.empty')
    @include($customPartial)
    @includeIf('orders.partials.missing')
    <x-dynamic-component :component="$widget" />

    @verbatim
        @include('verbatim.ignored')
    @endverbatim
@endsection
