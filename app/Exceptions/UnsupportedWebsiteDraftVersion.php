<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class UnsupportedWebsiteDraftVersion extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('This invitation draft uses an unsupported content version. Your saved draft has not been changed. Please contact support.');
    }

    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 409, ['Cache-Control' => 'no-store']);
        }

        return response($this->getMessage(), 409, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
