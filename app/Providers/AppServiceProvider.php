<?php declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $host   = env('ELASTIC_HOST', 'http://localhost:9200');
            $apiKey = env('ELASTIC_API_KEY');
            $user   = env('ELASTIC_USER');
            $pass   = env('ELASTIC_PASS', '');

            $b = ClientBuilder::create()
                ->setHosts([$host])
                ->setRetries(2);

            if ($apiKey) {
                $b->setApiKey($apiKey);
            } elseif ($user) {
                $b->setBasicAuthentication($user, $pass);
            }

            return $b->build();
        });
    }

    public function boot(): void {}
}
