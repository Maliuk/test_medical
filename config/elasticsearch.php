<?php

return [
    'class' => 'yii\elasticsearch\Connection',
    'nodes' => [
        ['http_address' => 'elasticsearch:9200'],
    ],
    // Autodetect is useful for cluster, but for single node we can skip it or set it
    'autodetectCluster' => false,
];
