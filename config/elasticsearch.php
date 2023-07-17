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