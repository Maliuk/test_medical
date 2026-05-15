<?php

return [
    'class' => \yii\queue\amqp_interop\Queue::class,
    'host' => 'rabbitmq',
    'port' => 5672,
    'user' => 'guest',
    'password' => 'guest',
    'queueName' => 'import-queue',
    'driver' => \yii\queue\amqp_interop\Queue::ENQUEUE_AMQP_LIB,
];
