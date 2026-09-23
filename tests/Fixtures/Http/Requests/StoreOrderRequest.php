<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;

final class StoreOrderRequest extends FormRequest
{
    /** Counts instantiations: only the opt-in rules reading may create one. */
    public static int $instances = 0;

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     */
    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        mixed $content = null,
    ) {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);

        self::$instances++;
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer',
            'status' => ['required', new In(['draft', 'placed'])],
            'note' => ['nullable', static function (string $attribute, mixed $value, callable $fail): void {}],
        ];
    }
}
