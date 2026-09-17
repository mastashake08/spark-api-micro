<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\OfficeController;
use Illuminate\Support\Facades\Route;

// All routes here proxy the Spark Platform API — SparkClient holds the
// access token server-side (config/services.php -> SPARK_ACCESS_TOKEN) and
// is never exposed to the frontend. See app/Services/SparkClient.php.
Route::prefix('listings')->group(function () {
    Route::get('/', [ListingController::class, 'index']);
    Route::get('/{id}', [ListingController::class, 'show']);
    Route::get('/{id}/photos', [ListingController::class, 'photos']);
    Route::get('/{id}/openhouses', [ListingController::class, 'openHouses']);
});

Route::prefix('accounts')->group(function () {
    Route::get('/', [AccountController::class, 'index']);
    Route::get('/{id}', [AccountController::class, 'show']);
});

Route::prefix('agents')->group(function () {
    Route::get('/', [AgentController::class, 'index']);
    Route::get('/{id}', [AgentController::class, 'show']);
});

Route::prefix('offices')->group(function () {
    Route::get('/', [OfficeController::class, 'index']);
    Route::get('/{id}', [OfficeController::class, 'show']);
});
