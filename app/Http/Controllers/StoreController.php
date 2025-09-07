<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class StoreController extends Controller
{
    private const INDEX = 'stores';
    public function __construct(private Client $es) {}

    public function create(Request $r): JsonResponse
    {
        $v = $r->validate([
            'name'=>['required','string','max:120'],
            'lat' =>['required','numeric','between:-90,90'],
            'lon' =>['required','numeric','between:-180,180'],
        ]);
        $id  = (string) Str::uuid();
        $doc = ['name'=>(string)$v['name'],'loc'=>['lat'=>(float)$v['lat'],'lon'=>(float)$v['lon']]];

        try {
            $this->es->index(['index'=>self::INDEX,'id'=>$id,'body'=>$doc,'refresh'=>'wait_for']);
            return response()->json(['id'=>$id,'data'=>$doc], 201);
        } catch (\Throwable $e) { return $this->esError($e); }
    }

    public function setArea(Request $r, string $id): JsonResponse
    {
        $v = $r->validate([
            'coordinates'=>['required','array','min:4'],
            'coordinates.*'=>['array','size:2'],
            'coordinates.*.0'=>['numeric','between:-180,180'],
            'coordinates.*.1'=>['numeric','between:-90,90'],
        ]);
        $ring = array_map(fn($p)=>[(float)$p[0],(float)$p[1]], $v['coordinates']);
        if ($ring[0] !== end($ring)) $ring[] = $ring[0];

        try { $this->es->get(['index'=>self::INDEX,'id'=>$id]); }
        catch (ClientResponseException $e) {
            if ($e->getResponse()->getStatusCode() === 404) return response()->json(['error'=>'not_found'],404);
            return $this->esError($e);
        }

        try {
            $this->es->update([
                'index'=>self::INDEX,'id'=>$id,'refresh'=>'wait_for',
                'body'=>['doc'=>['service_area'=>['type'=>'polygon','coordinates'=>[ $ring ]]]]
            ]);
            return response()->json(['ok'=>true]);
        } catch (\Throwable $e) { return $this->esError($e); }
    }

    public function nearest(Request $r): JsonResponse
    {
        $v=$r->validate([
            'lat'=>['required','numeric','between:-90,90'],
            'lon'=>['required','numeric','between:-180,180'],
            'radius_km'=>['sometimes','numeric','min:0.1','max:50'],
            'limit'=>['sometimes','integer','min:1','max:10'],
        ]);
        $lat=(float)$v['lat']; $lon=(float)$v['lon'];
        $km=min((float)($v['radius_km']??5),20); $lim=min((int)($v['limit']??3),10);

        try {
            $resp=$this->es->search([
                'index'=>self::INDEX,
                'body'=>[
                    'size'=>$lim,'track_total_hits'=>false,
                    'query'=>['bool'=>['filter'=>[['geo_distance'=>[
                        'distance'=>$km.'km','loc'=>['lat'=>$lat,'lon'=>$lon]
                    ]]]]],
                    'sort'=>[['_geo_distance'=>[
                        'loc'=>['lat'=>$lat,'lon'=>$lon],'order'=>'asc','unit'=>'km','mode'=>'min','distance_type'=>'arc'
                    ]]],
                    '_source'=>['name','loc']
                ]
            ])->asArray();

            $items=array_map(fn($h)=>[
                'id'=>$h['_id']??null,
                'name'=>$h['_source']['name']??null,
                'loc'=>$h['_source']['loc']??null,
                'distance_km'=>isset($h['sort'][0])?(float)$h['sort'][0]:null,
            ], $resp['hits']['hits']??[]);

            return response()->json(['items'=>$items]);
        } catch (\Throwable) { return response()->json(['items'=>[]]); }
    }

    public function eligible(Request $r): JsonResponse
    {
        $v=$r->validate([
            'lat'=>['required','numeric','between:-90,90'],
            'lon'=>['required','numeric','between:-180,180'],
        ]);
        $lat=(float)$v['lat']; $lon=(float)$v['lon'];

        try {
            $resp=$this->es->search([
                'index'=>self::INDEX,
                'body'=>[
                    'size'=>1,'track_total_hits'=>false,
                    'query'=>['bool'=>['filter'=>[['geo_shape'=>[
                        'service_area'=>['shape'=>['type'=>'point','coordinates'=>[$lon,$lat]],'relation'=>'intersects']
                    ]]]]],
                    '_source'=>['name','loc']
                ]
            ])->asArray();

            $hits=$resp['hits']['hits']??[];
            $stores=array_map(fn($h)=>[
                'id'=>$h['_id']??null,'name'=>$h['_source']['name']??null,'loc'=>$h['_source']['loc']??null,
            ],$hits);

            return response()->json(['eligible'=>!empty($hits),'stores'=>$stores]);
        } catch (\Throwable) { return response()->json(['eligible'=>false,'stores'=>[]]); }
    }

    public function within(Request $r): JsonResponse
    {
        $v=$r->validate([
            'min_lat'=>['required','numeric','between:-90,90'],
            'min_lon'=>['required','numeric','between:-180,180'],
            'max_lat'=>['required','numeric','between:-90,90'],
            'max_lon'=>['required','numeric','between:-180,180'],
            'limit'=>['sometimes','integer','min:1','max:500'],
        ]);
        $minLat=(float)$v['min_lat']; $minLon=(float)$v['min_lon'];
        $maxLat=(float)$v['max_lat']; $maxLon=(float)$v['max_lon'];
        $lim=(int)($v['limit']??200);

        try {
            $resp=$this->es->search([
                'index'=>self::INDEX,
                'body'=>[
                    'size'=>$lim,'track_total_hits'=>false,
                    'query'=>['bool'=>['filter'=>[['geo_bounding_box'=>[
                        'loc'=>[
                            'top_left'    =>['lat'=>$maxLat,'lon'=>$minLon],
                            'bottom_right'=>['lat'=>$minLat,'lon'=>$maxLon],
                        ]
                    ]]]]],
                    '_source'=>['name','loc']
                ]
            ])->asArray();

            $items=array_map(fn($h)=>[
                'id'=>$h['_id']??null,
                'name'=>$h['_source']['name']??null,
                'loc'=>$h['_source']['loc']??null,
            ], $resp['hits']['hits']??[]);

            return response()->json(['items'=>$items,'count'=>count($items)]);
        } catch (ClientResponseException|ServerResponseException) {
            return response()->json(['items'=>[],'count'=>0]); // indeks yok / ES kapalı
        } catch (\Throwable) {
            return response()->json(['items'=>[],'count'=>0]);
        }
    }

    public function heat(Request $r): JsonResponse
    {
        $z=(int)($r->validate(['z'=>['sometimes','integer','min:1','max:29']])['z']??7);

        try {
            $resp=$this->es->search([
                'index'=>self::INDEX,
                'body'=>[
                    'size'=>0,
                    'aggs'=>['grid'=>[
                        'geotile_grid'=>['field'=>'loc','precision'=>$z],
                        'aggs'=>['centroid'=>['geo_centroid'=>['field'=>'loc']]]
                    ]]
                ]
            ])->asArray();

            $tiles=array_map(function(array $b){
                $c=$b['centroid']['location']??null;
                return [
                    'key'=>$b['key']??null,
                    'doc_count'=>$b['doc_count']??0,
                    'centroid'=>$c?['lat'=>$c['lat'],'lon'=>$c['lon']]:null,
                ];
            }, $resp['aggregations']['grid']['buckets']??[]);

            return response()->json(['tiles'=>$tiles]);
        } catch (\Throwable) { return response()->json(['tiles'=>[]]); }
    }

    public function show(string $id): JsonResponse
    {
        try { $hit=$this->es->get(['index'=>self::INDEX,'id'=>$id])->asArray(); }
        catch (ClientResponseException $e) {
            if ($e->getResponse()->getStatusCode()===404) return response()->json(['error'=>'not_found'],404);
            return $this->esError($e);
        } catch (\Throwable $e) { return $this->esError($e); }

        $src=$hit['_source']??[];
        return response()->json([
            'id'=>$hit['_id']??$id,
            'name'=>$src['name']??null,
            'loc'=>$src['loc']??null,
            'service_area'=>$src['service_area']??null,
        ]);
    }

    private function esError(\Throwable $e): JsonResponse
    {
        $code = method_exists($e,'getResponse') && $e->getResponse() ? $e->getResponse()->getStatusCode() : 500;
        return response()->json(['error'=>'es_error','message'=>$e->getMessage()], $code);
    }
}
