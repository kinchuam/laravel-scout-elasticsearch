# laravel-scout-elasticsearch

Elasticsearch Driver for Laravel Scout

--- 

### Installation
You can install the package via composer:

```shell
composer require kinch/laravel-scout-elasticsearch
```

Laravel will automatically register the driver service provider.

Install elasticsearch-php client
For use this library we recomend using the version at this (^7.9). reference: [elasticsearch/elasticsearch](https://github.com/elastic/elasticsearch-php/tree/7.9)

```shell
composer require elasticsearch/elasticsearch:"^8.0"
```

### Setting up Elasticsearch configuration
After you've published the Laravel Scout package configuration, you need to set your driver to elasticsearch and add its configuration:
```php
// config/elasticsearch.php
<?php

return [
    'host' => env('ELASTICSEARCH_HOST'),
    'user' => env('ELASTICSEARCH_USER'),
    'password' => env('ELASTICSEARCH_PASSWORD'),
    'cert' => storage_path(env('ELASTICSEARCH_CERT')),

    'cloud_id' => env('ELASTICSEARCH_CLOUD_ID'),
    'api_key' => env('ELASTICSEARCH_API_KEY'),
    'queue' => [
        'timeout' => env('SCOUT_QUEUE_TIMEOUT'),
    ],
    
    // index_ is followed by the index name
    'index_article' => [
        'settings' => [
            'number_of_shards' => 5,
            'number_of_replicas' => 1,
        ],
        'mappings' => [
            "properties" => [
                "title" => [
                    "type" => "text",
                    "analyzer" => "ik_max_word",
                    "search_analyzer" => "ik_smart",
                    "fields" => ["keyword" => ["type" => "keyword", "ignore_above" => 256]],
                ],
            ],
        ],
    ],
];
```
### Usage
##### console
```shell
// create index
php artisan scout:index article

// delete index
php artisan scout:delete-index article

// batch update data to es
php artisan scout:import "App\Models\Article"

```
##### search example
```php
use App\Models\Article;

// $condition = "test";
// ... or
// $condition = [
//     "title" => "test",
//     "abstract" => "test"
// ];
// ... or
$keyword = "test";
$source = [1,2];
$startTime = '2023-05-01T00:00:00.000+0800';
$endTime = '2023-05-20T00:00:00.000+0800';
$condition = [
    "_customize_body" => 1,
    "bool" => [
        "should" => [
            [
                "match" => [
                    "title" => ["query" => $keyword, 'boost' => 5]
                ]
            ],
            [
                "match" => [
                    "abstract" => ["query" => $keyword, 'boost' => 3]
                ]
            ],
        ],
        "must" => [
            [
                "terms" => ["source" => $source]
            ],
            [
                "range" => [
                    "created_at" => [
                        'gte' => $startTime,
                        'lte' => $endTime
                    ]
                ]
            ]
        ]
    ],
     
];

$data = Article::search($condition)
        ->orderBy('_score', 'desc')
        ->paginate(10);
```
More please see [Laravel Scout official documentation](https://laravel.com/docs/10.x/scout).

### License
The MIT License (MIT).