<?php

use App\Http\Controllers\HealthController;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StoreController;


Route::get('/es/ping', function (Client $es) {
    return response()->json($es->info()->asArray());
});

Route::get('/healthz', fn() => response()->json(['ok' => true]));

Route::prefix('/')->middleware(['api','throttle:api'/*,'apikey'*/])->group(function () {
    Route::post('stores', [StoreController::class, 'create']);
    Route::post('stores/{id}/area', [StoreController::class, 'setArea']);
    Route::get('stores/near', [StoreController::class, 'nearest']);
    Route::get('delivery/eligible', [StoreController::class, 'eligible']);
    Route::get('stores/within', [StoreController::class, 'within']);
    Route::get('stores/heat', [StoreController::class, 'heat']);
    Route::get('stores/{id}', [StoreController::class, 'show']);
});
Route::get('/healthz', [HealthController::class,'health']);
Route::get('/readyz',  [HealthController::class,'ready']);
