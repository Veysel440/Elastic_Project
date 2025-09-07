<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Elastic\Elasticsearch\Client;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function health(): JsonResponse { return response()->json(['ok'=>true]); }

    public function ready(Client $es): JsonResponse {
        $info = $es->info()->asArray();
        return response()->json(['ok'=>true,'es'=>[
            'name'=>$info['name']??null, 'cluster'=>$info['cluster_name']??null, 'version'=>$info['version']['number']??null
        ]]);
    }
}
