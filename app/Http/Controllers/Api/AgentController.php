<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ValidatesSparkListParams;
use App\Http\Controllers\Controller;
use App\Services\SparkClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    use ValidatesSparkListParams;

    public function __construct(protected SparkClient $spark) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->spark->agents($this->listParams($request)));
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->spark->account($id));
    }
}
