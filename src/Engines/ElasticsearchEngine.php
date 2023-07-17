<?php

namespace Kinch\LaravelScoutElasticsearch\Engines;

use Exception;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Config;

final class ElasticsearchEngine extends Engine
{
    /**
     * Elasticsearch client.
     *
     * @var Client
     */
    protected $elasticsearch;

    /**
     * Determines if soft deletes for Scout are enabled or not.
     *
     * @var bool
     */
    protected $softDelete;

    /**
     * Create a new engine instance.
     *
     * @param Client $elasticsearch
     * @param bool $softDelete
     * @return void
     */
    public function __construct(Client $elasticsearch, bool $softDelete = false)
    {
        $this->elasticsearch = $elasticsearch;
        $this->softDelete = $softDelete;
    }

    /**
     * {@inheritdoc}
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            if (empty($model->toSearchableArray())) {
                return;
            }

            if ($model::usesSoftDelete() && $this->softDelete) {
                $model->pushSoftDeleteMetadata();
            }

            $routing = $model->routing;
            $scoutKey = $model->getScoutKey();
            $params['body'][] = [
                'index' => [
                    '_id' => $scoutKey,
                    '_index' => $model->searchableAs(),
                    'routing' => false === empty($routing) ? $routing : $scoutKey,
                ]
            ];

            $params['body'][] = array_merge(
                $model->toSearchableArray(),
                $model->scoutMetadata()
            );
        });

        $this->elasticsearch->bulk($params);
    }


    /**
     * {@inheritdoc}
     */
    public function delete($models)
    {
        $params = ['body' => []];

        $models->each(function ($model) use (&$params) {
            $routing = $model->routing;
            $scoutKey = $model->getScoutKey();
            $params['body'][] = [
                'delete' => [
                    '_id' => $scoutKey,
                    '_index' => $model->searchableAs(),
                    'routing' => false === empty($routing) ? $routing : $scoutKey,
                ],
            ];
        });

        $this->elasticsearch->bulk($params);
    }

    /**
     * {@inheritdoc}
     */
    public function flush($model)
    {
        $params = [
            'index' => $model->searchableAs()
        ];

        $this->elasticsearch->indices()->delete($params);
    }

    /**
     * {@inheritdoc}
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'size' => $builder->limit,
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'from' => ($page - 1) * $perPage,
            'size' => $perPage,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function mapIds($results)
    {
        if (0 === count($results['hits']['hits'])) {
            return collect();
        }

        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * {@inheritdoc}
     */
    public function map(Builder $builder, $results, $model)
    {
        if (is_null($results) || count($results['hits']['total']) === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($results['hits']['hits'])->pluck('_id')->values()->all();

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds(
            $builder, $objectIds
        )->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * {@inheritdoc}
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if (count($results['hits']['hits']) === 0) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results['hits'])->pluck('_id')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds(
            $builder, $objectIds
        )->cursor()->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * {@inheritdoc}
     */
    public function createIndex($name, array $options = [])
    {
        $params = [
            'index' => $name,
        ];

        $body = Config::get('scout.elasticsearch.index_' . $name);
        if ($body) {
            $params['body'] = $body;
        }

        $this->elasticsearch->indices()->create($params);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteIndex($name)
    {
        $this->elasticsearch->indices()->delete(['index' => $name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total']['value'];
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @param  array  $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $body = [];
        $condition = $builder->query;
        if (is_string($condition)) {
            // Full-text search
            $body['query']['bool']['must'] = [['query_string' => ['query' => "*{$condition}*"]]];
        } elseif (is_array($condition)) {
            // Customize the body request body
            if (isset($condition['_customize_body']) && $condition['_customize_body'] === 1) {
                unset($condition['_customize_body']);
                $body = $condition;
            } else {
                // Quickly search for multiple specified fields
                foreach ($condition as $k => $v) {
                    $body['query']['bool']['should'][] = ['match' => [$k => $v]];
                }
                $body['query']['bool']['minimum_should_match'] = 1;
            }
        } else {
            throw new Exception('The search criteria can only be strings or custom arrays');
        }

        $params = [
            'index' => $builder->index ?: $builder->model->searchableAs(),
            'body' => $body,
        ];

        if ($sort = $this->sort($builder)) {
            $params['body']['sort'] = $sort;
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        if (isset($options['numericFilters']) && count($options['numericFilters'])) {
            $params['body']['query']['bool']['must'] = array_merge(
                $params['body']['query']['bool']['must'],
                $options['numericFilters']
            );
        }

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->elasticsearch,
                $builder->query,
                $params
            );
        }

        return $this->elasticsearch->search($params);
    }

    /**
     * Get the filter array for the query.
     *
     * @param  Builder  $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            if (is_array($value) && $key != 'query') {
                return ['terms' => [$key => $value]];
            }
            if ($key == 'query') {
                return ['query_string' => $value];
            }
            return ['match_phrase' => [$key => $value]];
        })->values()->all();
    }


    /**
     * Generates the sort if theres any.
     *
     * @param  Builder $builder
     * @return array|null
     */
    protected function sort($builder)
    {
        if (count($builder->orders) == 0) {
            return null;
        }

        return collect($builder->orders)->map(function ($order) {
            return [$order['column'] => $order['direction']];
        })->toArray();
    }
}
