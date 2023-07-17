<?php

namespace Kinch\LaravelScoutElasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Laravel\Scout\EngineManager;
use Illuminate\Support\ServiceProvider;
use Kinch\LaravelScoutElasticsearch\Engines\ElasticsearchEngine;

final class LaravelScoutElasticsearchServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/elasticsearch.php', 'elasticsearch');

        $this->app->bind(Client::class, function () {
            $config = config('elasticsearch');

            $clientBuilder = ClientBuilder::create()->setHosts(explode(',', $config['host']));

            if ($config['user'] && $config['password']) {
                $clientBuilder->setBasicAuthentication($config['user'], $config['password']);
            }
    
            if ($config['cert']) {
                $clientBuilder->setCABundle($config['cert']);
            }
    
            if ($config['cloud_id']) {
                $clientBuilder->setElasticCloudId($config['cloud_id']);
            }
    
            if ($config['api_key']) {
                $clientBuilder->setApiKey($config['api_key']);
            }

            return $clientBuilder->build();
        });
    }
    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        $this->app->make(EngineManager::class)->extend(ElasticSearchEngine::class, function () {
            $elasticsearch = app(Client::class);

            return new ElasticSearchEngine($elasticsearch, config('scout.soft_delete', false));
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/elasticsearch.php' => config_path('elasticsearch.php'),
            ], 'config');
        }
    }

}