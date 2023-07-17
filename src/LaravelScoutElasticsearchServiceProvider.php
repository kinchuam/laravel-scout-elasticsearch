<?php

namespace Kinch\LaravelScoutElasticsearch;

use Elastic\Elasticsearch\Client;
use Laravel\Scout\EngineManager;
use Illuminate\Support\ServiceProvider;
use Kinch\LaravelScoutElasticsearch\Engines\ElasticsearchEngine;

final class LaravelScoutElasticsearchServiceProvider extends ServiceProvider
{

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'scout');

        $this->app->make(EngineManager::class)->extend(ElasticSearchEngine::class, function () {
            $elasticsearch = app(Client::class);

            return new ElasticSearchEngine($elasticsearch);
        });
    }

}