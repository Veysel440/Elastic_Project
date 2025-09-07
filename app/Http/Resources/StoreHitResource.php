<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @property array $resource */
final class StoreHitResource extends JsonResource
{
    public function toArray($request): array
    {
        $hit = $this->resource;
        $src = $hit['_source'] ?? [];

        return [
            'id'          => $hit['_id'] ?? null,
            'name'        => $src['name'] ?? null,
            'loc'         => $src['loc']  ?? null,
            'distance_km' => isset($hit['sort'][0]) ? (float) $hit['sort'][0] : null,
        ];
    }
}
