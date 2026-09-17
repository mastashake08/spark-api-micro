<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ValidatesSparkListParams;
use App\Http\Controllers\Controller;
use App\Services\SparkClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agents and offices are both `/accounts` records distinguished by
 * `UserType` — see AgentController/OfficeController for the filtered
 * convenience wrappers, matching the npm package's client.agents/offices.
 */
class AccountController extends Controller
{
    use ValidatesSparkListParams;

    public function __construct(protected SparkClient $spark) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->spark->accounts($this->listParams($request)));
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->spark->account($id));
    }
}
