<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wraps a failed Spark API call (a transport failure, or `D.Success: false`
 * in the response envelope) with the upstream status/code/details so
 * controllers can let it bubble up and have it render as JSON automatically.
 */
class SparkApiException extends Exception
{
    /**
     * @param  array<int, array{Message?: string, Code?: string, Field?: string}>  $details
     */
    public function __construct(
        string $message,
        public readonly int $status = 502,
        public readonly ?string $errorCode = null,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'code' => $this->errorCode,
            'details' => $this->details,
        ], $this->status);
    }
}
