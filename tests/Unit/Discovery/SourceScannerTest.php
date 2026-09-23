<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use LaBoiteACode\DependencyGraph\Discovery\Support\SourceScanner;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Scanning\ScannedSubject;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Scanning\ShippedJob;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Scanning\ShippedMail;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Invoice;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Product;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\User;

function scannedMethod(string $method, string $class = ScannedSubject::class): LaBoiteACode\DependencyGraph\Discovery\Support\MethodSource
{
    $source = (new SourceScanner)->method(new ReflectionMethod($class, $method));

    expect($source)->not->toBeNull();

    return $source;
}

it('reads the namespace and every namespace-level import form', function (): void {
    $file = (new SourceScanner)->file((new ReflectionClass(ScannedSubject::class))->getFileName());

    expect($file->namespace)->toBe('LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Scanning')
        ->and($file->imports)->toBe([
            'Mail' => Mail::class,
            'Order' => Order::class,
            'Item' => Product::class,
            'Account' => User::class,
        ]);
});

it('ignores trait and closure imports', function (): void {
    $file = (new SourceScanner)->parse(<<<'PHP'
        <?php
        namespace App;
        use App\Models\Order;
        class Foo {
            use SomeTrait;
            public function run() { return function () use ($x) {}; }
        }
        PHP);

    expect($file->imports)->toBe(['Order' => 'App\Models\Order']);
});

it('resolves aliases, fully qualified and namespace-relative names', function (): void {
    $scanner = new SourceScanner;
    $imports = ['Item' => Product::class];

    expect($scanner->resolveClass('Item', 'App', $imports))->toBe(Product::class)
        ->and($scanner->resolveClass('Item\\Variant', 'App', $imports))->toBe(Product::class . '\\Variant')
        ->and($scanner->resolveClass('\\Foo\\Bar', 'App', $imports))->toBe('Foo\\Bar')
        ->and($scanner->resolveClass('namespace\\Bar', 'App', $imports))->toBe('App\\Bar')
        ->and($scanner->resolveClass('Bar', 'App', $imports))->toBe('App\\Bar')
        ->and($scanner->resolveClass('static', 'App', $imports))->toBeNull()
        ->and($scanner->resolveClass('self', 'App', $imports))->toBeNull();
});

it('lists static class references of a method, ignoring comments and strings', function (): void {
    $classes = array_column(scannedMethod('handle')->staticClassReferences(), 'class');

    expect($classes)->toContain(Order::class, Product::class, User::class, Mail::class, ShippedJob::class)
        ->not->toContain(Invoice::class)
        ->not->toContain('LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Scanning\Customer');
});

it('lists static method calls with their method names', function (): void {
    $calls = array_map(
        static fn (array $call): string => $call['class'] . '::' . $call['method'],
        scannedMethod('handle')->staticCalls(),
    );

    expect($calls)->toBe([
        Order::class . '::query',
        Product::class . '::where',
        Mail::class . '::to',
        ShippedJob::class . '::dispatch',
    ]);
});

it('lists named instantiations only', function (): void {
    expect(array_column(scannedMethod('handle')->instantiations(), 'class'))->toBe([
        ShippedMail::class,
        ShippedJob::class,
    ]);
});

it('lists collaborator calls on variables and properties', function (): void {
    $calls = array_map(
        static fn (array $call): string => ($call['variable'] !== null ? '$' . $call['variable'] : '$this->' . $call['property'])
            . '->' . $call['method'],
        scannedMethod('handle')->collaboratorCalls(),
    );

    expect($calls)->toBe([
        '$action->execute',
        '$action->undo',
        '$this->orders->create',
        '$callback->__invoke',
        '$action->execute',
    ]);
});

it('records the line of every finding', function (): void {
    $calls = scannedMethod('handle')->staticCalls();

    expect($calls[0]['line'])->toBe(24);
});

it('reads methods declared in traits from the trait file', function (): void {
    expect(array_column(scannedMethod('fromTrait')->staticClassReferences(), 'class'))->toBe([Invoice::class]);
});

it('returns an empty body for empty methods and null for unknown sources', function (): void {
    $scanner = new SourceScanner;

    expect(scannedMethod('empty')->staticCalls())->toBe([])
        ->and($scanner->method(new ReflectionMethod(ArrayObject::class, 'count')))->toBeNull()
        ->and($scanner->file('/missing/file.php'))->toBeNull();
});

it('finds the first literal rendered view', function (): void {
    $scanner = new SourceScanner;

    expect($scanner->renderedView($scanner->parse('<?php return view("livewire.orders", []);')))->toBe('livewire.orders')
        ->and($scanner->renderedView($scanner->parse('<?php return view($name);')))->toBeNull();
});
