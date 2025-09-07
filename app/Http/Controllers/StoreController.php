<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\{StoreCreateRequest, StoreSetAreaRequest, NearRequest, HeatRequest};
use Elastic\Elasticsearch\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class StoreController extends Controller
{
    public function __construct(private Client $es) {}

    public function create(StoreCreateRequest $r): JsonResponse
    {
        $id  = (string) Str::uuid();
        $doc = [
            'name' => $r->validated('name'),
            'loc'  => ['lat' => (float) $r->validated('lat'), 'lon' => (float) $r->validated('lon')],
        ];

        $this->es->index([
            'index'   => 'stores',
            'id'      => $id,
            'body'    => $doc,
            'refresh' => 'wait_for',
        ]);

        return response()->json(['id' => $id, 'data' => $doc], 201);
    }
    public function show(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $hit = $this->es->get(['index'=>'stores','id'=>$id])->asArray();
        } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return response()->json(['error'=>'not_found'], 404);
            }
            throw $e;
        }
        $src = $hit['_source'] ?? [];
        return response()->json([
            'id' => $hit['_id'] ?? $id,
            'name' => $src['name'] ?? null,
            'loc' => $src['loc'] ?? null,
            'service_area' => $src['service_area'] ?? null,
        ]);
    }

    public function setArea(string $id, StoreSetAreaRequest $r): JsonResponse
    {
        $coords = $r->validated('coordinates');
        // lon,lat çiftlerinden oluşan closed ring'e çevir
        if ($coords[0] !== $coords[array_key_last($coords)]) {
            $coords[] = $coords[0];
        }

        $this->es->update([
            'index'   => 'stores',
            'id'      => $id,
            'refresh' => 'wait_for',
            'body'    => ['doc' => [
                'service_area' => [
                    'type'        => 'polygon',
                    'coordinates' => [ $coords ],
                ],
            ]],
        ]);

        return response()->json(['ok' => true]);
    }

    public function nearest(NearRequest $r): JsonResponse
    {
        $lat = (float) $r->validated('lat');
        $lon = (float) $r->validated('lon');
        $km  = (float) ($r->validated('radius_km') ?? 5.0);
        $lim = (int)   ($r->validated('limit') ?? 3);

        $resp = $this->es->search([
            'index' => 'stores',
            'body'  => [
                'size'  => $lim,
                'query' => [
                    'bool' => [
                        'filter' => [[
                            'geo_distance' => [
                                'distance' => $km . 'km',
                                'loc'      => ['lat' => $lat, 'lon' => $lon],
                            ],
                        ]],
                    ],
                ],
                'sort' => [[
                    '_geo_distance' => [
                        'loc'   => [$lon, $lat],
                        'order' => 'asc',
                        'unit'  => 'km',
                        'mode'  => 'min',
                    ],
                ]],
                '_source' => ['name','loc'],
            ],
        ])->asArray();

        $items = array_map(function (array $hit) {
            $src = $hit['_source'] ?? [];
            return [
                'id'          => $hit['_id'] ?? null,
                'name'        => $src['name'] ?? null,
                'loc'         => $src['loc']  ?? null,
                'distance_km' => isset($hit['sort'][0]) ? (float) $hit['sort'][0] : null,
            ];
        }, $resp['hits']['hits'] ?? []);

        return response()->json([
            'items' => $items,
            'count' => count($items),
        ]);
    }

    public function eligible(NearRequest $r): JsonResponse
    {
        $lat = (float) $r->validated('lat');
        $lon = (float) $r->validated('lon');

        $resp = $this->es->search([
            'index' => 'stores',
            'body'  => [
                'size'  => 10,
                'query' => [
                    'geo_shape' => [
                        'service_area' => [
                            'relation' => 'intersects',
                            'shape'    => ['type' => 'point', 'coordinates' => [$lon, $lat]],
                        ],
                    ],
                ],
                '_source' => ['name','loc','service_area'],
            ],
        ])->asArray();

        $items = array_map(fn ($h) => [
            'id'   => $h['_id'] ?? null,
            'name' => $h['_source']['name'] ?? null,
        ], $resp['hits']['hits'] ?? []);

        return response()->json([
            'eligible' => !empty($items),
            'stores'   => $items,
        ]);
    }

    public function heat(HeatRequest $r): JsonResponse
    {
        $precision = (int) $r->validated('z'); // 1..29

        $resp = $this->es->search([
            'index' => 'stores',
            'body'  => [
                'size' => 0,
                'aggs' => [
                    'tiles' => [
                        'geotile_grid' => ['field' => 'loc', 'precision' => $precision],
                        'aggs'         => ['centroid' => ['geo_centroid' => ['field' => 'loc']]],
                    ],
                ],
            ],
        ])->asArray();

        $buckets = $resp['aggregations']['tiles']['buckets'] ?? [];
        $items   = array_map(fn ($b) => [
            'key'        => $b['key'],
            'doc_count'  => $b['doc_count'],
            'centroid'   => $b['centroid']['location'] ?? null, // {lat, lon}
        ], $buckets);

        return response()->json(['tiles' => $items]);
    }

    public function within(\App\Http\Requests\WithinRequest $r): \Illuminate\Http\JsonResponse
    {
        $minLat = (float) $r->validated('min_lat');
        $minLon = (float) $r->validated('min_lon');
        $maxLat = (float) $r->validated('max_lat');
        $maxLon = (float) $r->validated('max_lon');
        $lim    = (int)   ($r->validated('limit') ?? 200);

        $resp = $this->es->search([
            'index'=>'stores',
            'body'=>[
                'size'=>$lim,
                'track_total_hits'=>false,
                'query'=>[
                    'bool'=>['filter'=>[
                        ['geo_bounding_box'=>[
                            'loc'=>[
                                'top_left'     => ['lat'=>$maxLat,'lon'=>$minLon],
                                'bottom_right' => ['lat'=>$minLat,'lon'=>$maxLon],
                            ]
                        ]]
                    ]]
                ],
                '_source'=>['name','loc']
            ]
        ])->asArray();

        $items = array_map(fn($h)=>[
            'id'=>$h['_id']??null, 'name'=>$h['_source']['name']??null, 'loc'=>$h['_source']['loc']??null
        ], $resp['hits']['hits'] ?? []);

        return response()->json(['items'=>$items,'count'=>count($items)]);
    }
}
