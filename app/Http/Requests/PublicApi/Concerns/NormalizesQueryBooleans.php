<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicApi\Concerns;

trait NormalizesQueryBooleans
{
    /** @param list<string> $keys */
    protected function normalizeQueryBooleans(array $keys): void
    {
        $normalized = [];

        foreach ($keys as $key) {
            $value = $this->query($key);

            if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
                $normalized[$key] = strtolower($value) === 'true';
            }
        }

        $this->merge($normalized);
    }
}
