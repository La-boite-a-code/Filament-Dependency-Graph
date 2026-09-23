<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Scanning;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\View;

final class ViewsSubject
{
    public function render(): mixed
    {
        view('orders.index');
        View::make('orders.show');
        \View::make('orders.legacy');
        response()->view('errors.custom');
        $this->layout('layouts.admin');
        (new Content(view: 'mail.html', markdown: 'mail.markdown', text: 'mail.text'));
        $message->markdown('mail.notification');
        \view('orders.qualified');
        view()->make('orders.helper');
        (new Content(html: 'mail.html-version'));
        $this->extends('layouts.legacy');
        view('orders.' . $type);
        view($dynamic);
        $collection->view();
        $text = 'view("in.a.string")';

        return null;
    }
}
