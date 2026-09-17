<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ValidatesSparkListParams;
use App\Http\Controllers\Controller;
use App\Services\SparkClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    use ValidatesSparkListParams;

    public function __construct(protected SparkClient $spark) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->spark->listings($this->listParams($request)));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'select' => ['sometimes', 'string'],
            'expand' => ['sometimes', 'string'],
        ]);

        return response()->json($this->spark->listing($id, $validated));
    }

    public function photos(string $id): JsonResponse
    {
        return response()->json($this->spark->media($id));
    }

    public function openHouses(string $id): JsonResponse
    {
        return response()->json($this->spark->openHouses($id));
    }
}
