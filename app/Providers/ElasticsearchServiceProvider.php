<?php declare(strict_types=1);

namespace App\Providers;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

final class ElasticsearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $hosts = array_map('trim', explode(',', config('elasticsearch.hosts', 'http://localhost:9200')));
            return ClientBuilder::create()->setHosts($hosts)->build();
        });
    }
}
