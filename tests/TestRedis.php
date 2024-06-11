<?php

require 'vendor/autoload.php';

try {
    // 创建 Redis 客户端
    $client = new Predis\Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);

    // 测试连接是否正常
    $client->connect();
    echo "连接成功！" . PHP_EOL;

    // 设置一个键值对
    $client->set('test_key', 'Hello, Redis!');
    echo "设置成功：" . $client->get('test_key') . PHP_EOL;

    // 删除测试键值对
    $client->del(['test_key']);
    echo "删除成功！" . PHP_EOL;

} catch (Exception $e) {
    echo "连接失败：" . $e->getMessage() . PHP_EOL;
}
