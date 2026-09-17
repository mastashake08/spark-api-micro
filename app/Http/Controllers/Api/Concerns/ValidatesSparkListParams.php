<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait ValidatesSparkListParams
{
    /** @return array<string, mixed> */
    protected function listParams(Request $request): array
    {
        return $request->validate([
            'filter' => ['sometimes'],
            'filter.*' => ['string'],
            'select' => ['sometimes', 'string'],
            'expand' => ['sometimes', 'string'],
            'orderBy' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
    }
}
