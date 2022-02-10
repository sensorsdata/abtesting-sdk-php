<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once("lib/cache.php");
require_once("lib/LRUCache.php");

final class TestCache extends TestCase {
    public function testLRUCacheCapacity() {
        $cache = new LRUCache('redis', 'localhost', '6379', 'test', 5);
        $cache->put('1', 'a');
        $cache->put('2', 'b');
        $cache->put('3', 'c');
        $cache->put('4', 'd');
        $cache->put('5', 'e');
        $cache->put('6', 'f');
        $this->assertSame($cache->get('1'), null);
        $this->assertSame($cache->get('2'), 'b');
    }


    /**
     * 设置两个node，过期时间5s，cache_size=2
     * sleep 6s，cache_size=0
     */
    public function testCacheCapacityAfterExpire() {
        $cache = new LRUCache('redis', 'localhost', '6379', 'test', 5, 5);
        $cache->put('1', 'a');
        $cache->put('2', 'b');
        $this->assertSame($cache->cache_size(), 2);
        sleep(1);
        $this->assertSame($cache->cache_size(), 2);
        sleep(5);
        $this->assertSame($cache->cache_size(), 0);
    }

    /**
     * 设置两个node，过期时间5s，cache_size=2
     * sleep 6s，cache_size=0
     */
    public function testMemcachedCacheCapacityAfterExpire() {
        $cache = new LRUCache('memcached', 'localhost', '11211', 'test', 5, 5);
        $cache->put('1', 'a');
        $cache->put('2', 'b');
        $this->assertSame($cache->cache_size(), 2);
        sleep(1);
        $this->assertSame($cache->cache_size(), 2);
        sleep(5);
        $this->assertSame($cache->cache_size(), 0);
    }
}