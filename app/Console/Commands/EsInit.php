<?php declare(strict_types=1);

namespace App\Console\Commands;

use Elastic\Elasticsearch\Client;
use Illuminate\Console\Command;

final class EsInit extends Command
{
    protected $signature = 'es:init';
    protected $description = 'Create mappings for stores index if missing';

    public function handle(Client $es): int
    {
        $es->indices()->create([
            'index' => 'stores',
            'body'  => [
                'mappings' => [
                    'properties' => [
                        'name'         => ['type' => 'keyword'],
                        'loc'          => ['type' => 'geo_point'],
                        'service_area' => ['type' => 'geo_shape'],
                    ],
                ],
            ],
            'client' => ['ignore' => [400]],
        ]);

        $this->info('stores index ready');
        return self::SUCCESS;
    }
}
