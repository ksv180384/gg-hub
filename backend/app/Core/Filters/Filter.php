<?php

declare(strict_types=1);

namespace App\Core\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class Filter
{
    public const KEYS_TO_BOOL = [];
    public const KEYS_TO_INT = [];
    public const KEYS_TO_DATE = [];
    public const KEYS_STRING_TO_ARRAY = [];
    public const KEYS_TO_ARRAY = [];

    protected Builder $builder;

    public function __construct(
        protected readonly FormRequest $request
    ) {}

    /**
     * Применение фильтров к запросу.
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->request->input() as $method => $value) {
            $methodName = Str::camel($method);

            if ($value === null) {
                continue;
            }

            if (! method_exists($this, $methodName)) {
                continue;
            }

            if (in_array($method, static::KEYS_TO_BOOL, true)) {
                $value = (bool) $value;
            }

            if (in_array($method, static::KEYS_TO_INT, true)) {
                $value = (int) $value;
            }

            if (in_array($method, static::KEYS_TO_DATE, true)) {
                $value = CarbonImmutable::parse($value);
            }

            if (in_array($method, static::KEYS_TO_ARRAY, true)) {
                $value = is_array($value) ? $value : [$value];
            }

            if (in_array($method, static::KEYS_STRING_TO_ARRAY, true)) {
                $value = is_string($value) ? explode(',', $value) : (array) $value;
            }

            $this->builder = $this->{$methodName}($value);
        }

        return $this->builder;
    }
}
