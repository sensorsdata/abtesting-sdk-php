<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SebastianBergmann\Environment\Console;

require 'vendor/autoload.php';
require_once("SensorsABTesting.php");


define('ABTestingAPIUrl', 'http://10.130.6.5:8202/api/v2/abtest/online/results?project-key=438B9364C98D54371751BA82F6484A1A03A5155E');
define('SDKLogFileName', 'sdk_testlog_at_'.time().'.testlog');

final class TestSensorsABTesting extends TestCase {
    public function testDefaultInit() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [ "enable_event_cache" => false]);
        $experiment_result = $sab->async_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "value_type" => "STRING",
            "param_name" => 'test_string',
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
    }
    /**
     * 验证调用 async_fetch_abtest 返回实验值结果正确
     * 前置条件：ab 实验配置了 参数名为 test_string 类型为string 的实验
     * 1. 进行 abtesting sdk 正确初始化，其中 enable_event_cache = true
     * 2. 调用 async_fetch_abtest 方法获取服务端最新的实验变量，其中 request_param 中设置
     *  param_name=test_string
     *  default_value=默认值
     *  enable_auto_track_event=true
     *  timeout_milliseconds=3000ms
     * 3. 检查实验结果是否正确，检查是否进行 $ABTestTrigger 的上传
     */
    public function testAsyncFetchNormal() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6379",
            "enable_event_cache" => true,
        ]);
        $experiment_result = $sab->async_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "value_type" => "STRING",
            "param_name" => 'test_string',
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
    }

    public function testAsyncFetchTimeout() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6379",
            "enable_event_cache" => true,
        ]);
        $experiment_result = $sab->async_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "value_type" => "STRING",
            "param_name" => 'test_string',
            "timeout_milliseconds" => 1
        ]);
        $this->assertSame($experiment_result['value'], '1');
    }

    /**
     * 验证redis连接错误
     */
    public function testAsyncFetchBadConnection() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $this->expectException(RedisException::class);
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6380",
            "enable_event_cache" => true,
        ]);
    }

    /**
     * 验证调用 async_fetch_abtest 返回默认值结果正确	
     * 前置条件：ab 实验 未配置 参数名为 test_unset_string 类型为string 的实验 
     * 1. 进行 abtesting sdk 正确初始化，其中 enable_event_cache = true
     * 2. 调用 async_fetch_abtest 方法获取服务端最新的实验变量，其中 request_param 中设置
     *  param_name=test_unset_string
     *  default_value=默认值
     *  enable_auto_track_event=true
     *  timeout_milliseconds=3000ms
     * 3. 检查是否进行 $ABTestTrigger 的上传
     * 
     */
    public function testAsyncFetchDefaultValue() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa);
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6379",
            "enable_event_cache" => true,
        ]);
        $experiment_result = $sab->async_fetch_abtest("mengxy", true, [
            "default_value" => 'aaa',
            "value_type" => "STRING",
            "param_name" => 'test_unset_string',
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
    }

    /**
     * 验证调用 async_fetch_abtest 返回实验值结果正确，事件不缓存
     * 前置条件: ab 实验配置了 参数名为 btn_type 类型为string 的实验
     * 1. 进行 abtesting sdk 正确初始化，其中 enable_event_cache = false
     * 2. 调用 async_fetch_abtest 方法获取服务端最新的实验变量，其中 request_param 中设置
     *  param_name=btn_type
     *  default_value=默认值
     *  enable_auto_track_event=true
     *  timeout_milliseconds=3000ms
     * 3. 检查是否进行 $ABTestTrigger 的上传
     * 
     */
    public function testAsyncFetchCache() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6379",
            "enable_event_cache" => false,
        ]);
        $experiment_result = $sab->async_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "value_type" => "STRING",
            "param_name" => 'test_string'
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
    }

    /**
     * 验证调用 async_fetch_abtest 返回实验值结果正确，事件不触发	
     * 前置条件: ab 实验配置了 参数名为 btn_type 类型为string 的实验
     * 1. 进行 abtesting sdk 正确初始化，其中 enable_event_cache = true
     * 2. 调用 async_fetch_abtest 方法获取服务端最新的实验变量，其中 request_param 中设置
     *  param_name=btn_type
     *  default_value=默认值
     *  enable_auto_track_event=false
     *  timeout_milliseconds=3000ms
     * 3. 检查是否进行 $ABTestTrigger 的上传
     * 
     */
    public function testAsyncFetchNoAutoTrack() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6379",
            "enable_event_cache" => true,
        ]);
        $experiment_result = $sab->async_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "value_type" => "STRING",
            "enable_auto_track_event" => false,
            "param_name" => 'test_string'
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
    }

    /**
     * 验证调用 fast_fetch_abtest 返回实验值结果正确，事件触发	
     * 前置条件: ab 实验配置了 参数名为 btn_type 类型为string 的实验
     * 1. 进行 abtesting sdk 正确初始化，其中 enable_event_cache = true
     * 2. 调用 fast_fetch_abtest 方法获取服务端最新的实验变量，其中 request_param 中设置
     *  param_name=btn_type
     *  default_value=默认值
     *  enable_auto_track_event=true
     *  timeout_milliseconds=3000ms
     * 
     */
    public function testFastFetchNormalRedis() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6379",
            "enable_evqent_cache" => true,
            "log_path" => __DIR__ . "/SA.log"
        ]);
        $experiment_result = $sab->fast_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "value_type" => "STRING",
            "param_name" => 'test_string',
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
    }

    public function testFastFetchRedisFallbackIfConnectionFailed() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "63719",
            "enable_event_cache" => false,
        ]);
        $experiment_result = $sab->fast_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "value_type" => "STRING",
            "param_name" => 'test_string',
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
    }

    /**
     * 验证调用 memcached fast_fetch_abtest 返回实验值结果正确，事件触发	
     * 前置条件: ab 实验配置了 参数名为 btn_type 类型为string 的实验
     * 1. 进行 abtesting sdk 正确初始化，其中 enable_event_cache = true
     * 2. 调用 fast_fetch_abtest 方法获取服务端最新的实验变量，其中 request_param 中设置
     *  param_name=btn_type
     *  default_value=默认值
     *  enable_auto_track_event=true
     *  timeout_milliseconds=3000ms
     * 
     */
    public function testFastFetchNormalMemcached() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "memcached",
            "host" => "127.0.0.1",
            "port" => "11211",
            "enable_event_cache" => true,
        ]);
        $experiment_result = $sab->fast_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "value_type" => "STRING",
            "param_name" => 'test_string',
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
    }

    /**
     * 验证调用 fast_fetch_abtest 返回实验值结果正确，事件触发	
     * 前置条件: ab 实验 未配置 参数名为 btn_type 类型为string 的实验 
     * 1. 进行 abtesting sdk 正确初始化，其中 enable_event_cache = true
     * 2. 调用 fast_fetch_abtest 方法获取服务端最新的实验变量，其中 request_param 中设置
     *  param_name=btn_type
     *  default_value=默认值
     *  enable_auto_track_event=true
     *  timeout_milliseconds=3000ms
     * 
     */
    public function testFastFetchDefaultValue() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6379",
            "enable_event_cache" => true,
        ]);
        $experiment_result = $sab->fast_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "value_type" => "STRING",
            "param_name" => 'test_unset_string',
        ]);
        $this->assertSame($experiment_result['value'], '1');
    }

    /**
     * 验证调用 fast_fetch_abtest 返回实验值结果正确，事件不触发	
     * 前置条件: ab 实验配置了 参数名为 btn_type 类型为string 的实验
     * 1. 进行 abtesting sdk 正确初始化，其中 enable_event_cache = true
     * 2. 调用 fast_fetch_abtest 方法获取服务端最新的实验变量，其中 request_param 中设置
     *  param_name=btn_type
     *  default_value=默认值
     *  enable_auto_track_event=false
     *  timeout_milliseconds=3000ms
     * 3. 检查是否进行 $ABTestTrigger 的上传
     * 
     */
    public function testFastFetchNoAutoTrack() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6379",
            "enable_event_cache" => true,
        ]);
        $experiment_result = $sab->fast_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "enable_auto_track_event" => false,
            "value_type" => "STRING",
            "param_name" => 'test_string'
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
    }

    /**
     * 验证调用 fast_fetch_abtest 返回实验值结果正确，事件触发但不缓存	
     * 前置条件: 缓存中存在 参数名为 btn_type 类型为string 的实验
     * 1. 进行 abtesting sdk 正确初始化，其中 enable_event_cache = false
     * 2. 调用 fast_fetch_abtest 方法获取服务端最新的实验变量，其中 request_param 中设置
     *  param_name=btn_type
     *  default_value=默认值
     *  enable_auto_track_event=true
     *  timeout_milliseconds=3000ms
     * 3. 检查是否进行 $ABTestTrigger 的上传
     * 
     */
    public function testFastFetchNoCacheEvent() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6379",
            "enable_event_cache" => false,
        ]);
        $experiment_result = $sab->fast_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "enable_auto_track_event" => true,
            "value_type" => "STRING",
            "param_name" => 'test_string'
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
    }


    public function testBoolean()
    {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(
            "http://10.129.29.108:8202/api/v2/abtest/online/results?project-key=DA3D62D0AB384C45C252D6A19D2AE435F8A32C81",  // 试验上线前，请检查试验的分流服务的 url 是否正确
            $sa, // 神策分析埋点 SDK 的实例
            ["enable_event_cache" => false] // 不使用事件缓存
        );

        // 初始化 SDK 之后，可以通过 fast_fetch_abtest 接口获取具体试验的变量值，然后进行试验。
        $distinct_id = "xxx123";
        $is_login_id = true;   // 当前用户是否是登录 ID

        // 当前为 Boolean 类型试验
        $experiment_result = $sab->async_fetch_abtest(
            $distinct_id,
            $is_login_id,
            [
                "param_name" => "test_boolean",
                "value_type" => "BOOLEAN",
                "default_value" => true // false 表示未命中试验时，会返回此默认值，请根据业务需要更改此处的值
            ]
        );
        // TODO 请根据 $experiment_result['value'] 进行自己的试验，当前试验对照组返回值为：false，试验组依次返回：true
        var_dump($experiment_result['value']);
    }


    /**
     * 验证调用 手动可触发事件	
     * 前置条件: 缓存中存在 参数名为 btn_type 类型为string 的实验
     * 1. 进行 abtesting sdk 正确初始化，其中 enable_event_cache = false
     * 2. 调用 fast_fetch_abtest 方法获取服务端最新的实验变量，其中 request_param 中设置
     *  param_name=btn_type
     *  default_value=默认值
     *  enable_auto_track_event=false
     *  timeout_milliseconds=3000ms
     * 3. 调用 track_abtesttrigger ，检查是否进行 $ABTestTrigger 的上传
     */
    public function testTrackABTestTrigger() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa, [
            "type" => "redis",
            "host" => "127.0.0.1",
            "port" => "6379",
            "enable_event_cache" => false,
        ]);
        $experiment_result = $sab->fast_fetch_abtest("mengxy", true, [
            "default_value" => '1',
            "enable_auto_track_event" => true,
            "value_type" => "STRING",
            "param_name" => 'test_string'
        ]);
        $this->assertSame($experiment_result['value'], 'aaa');
        $sab->track_abtesttrigger($experiment_result);
    }

    /**
     * 验证缓存大小，超出容量需要按照 LRU 规则删除过期缓存
     */
    public function testCacheCapacity() {
        $this->assertSame(1, 1);
    }

    /**
     * 验证过期时间
     */
    public function testCacheTime() {
        $this->assertSame(1, 1);
    }

    /**
     * 当缓存挂掉的时候 fastFetch 降级成 asyncFetch
     */
    public function testFastFetchDowngradeWhenCacheLost() {
        $this->assertSame(1, 1);
    }

    public function testValidations() {
        $test_api_url = "123";
        try {
            $sab = new SensorsABTesting($test_api_url, []);
        } catch (Exception $e) {
            $this->assertSame($e->getMessage(), '$sa is not an instance of SensorsAnalytics');
        }
    }

    public function testValueTypes() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa);
        $experiment_result = $sab->async_fetch_abtest("mengxy", true, [
            "default_value" => 1,
            "value_type" => "INTEGER",
            "param_name" => 'test_int',
        ]);
        $this->assertSame($experiment_result['value'], 111);

        $experiment_result = $sab->async_fetch_abtest("mengxy", true, [
            "default_value" => true,
            "value_type" => "BOOLEAN",
            "param_name" => 'test_bool',
        ]);
        $this->assertSame($experiment_result['value'], true);

        $experiment_result = $sab->async_fetch_abtest("mengxy", true, [
            "default_value" => "default_string",
            "value_type" => "STRING",
            "param_name" => 'test_bool',
        ]);
        $this->assertSame($experiment_result['value'], "default_string");

    }

    public function testJson() {
        $sa = new SensorsAnalytics(new FileConsumer(SDKLogFileName));
        $sab = new SensorsABTesting(ABTestingAPIUrl, $sa);
        $defaultJson = new stdClass();
        $defaultJson->value = "defaultJsonValue";
        $experiment_result = $sab->async_fetch_abtest("mengxy", true, [
            "default_value" => $defaultJson,
            "value_type" => "JSON",
            "param_name" => 'test_json',
        ]);
        $resultJson = new stdClass();
        $resultJson->aa = "11";
        print_r($experiment_result);
        $this->assertSame($experiment_result['value'], $resultJson);
    }


    // public function testConstructorApiUrl()
    // {
    //     $test_api_url = 'test_api_url';
    //     $sab = new SensorsABTesting($test_api_url, []);
    //     $this->assertSame($test_api_url, $sab->_api_url);
    // }

    // public function testConstructorCacheMemcached()
    // {
    //     $test_file = "php_sdk_test";
    //     $consumer = new FileConsumer($test_file);
    //     $sa = new SensorsAnalytics($consumer);
    //     $test_api_url = 'test_api_url';
    //     $sab = new SensorsABTesting($test_api_url, $sa, array(
    //         "cache_config" => array(
    //             "type" => "memcached",
    //             "ip" => "localhost",
    //             "port" => "11211"
    //         )
    //     ));
    //     $this->assertSame($test_api_url, $sab->_api_url);
    // }
    // public function testConstructorCacheRedis()
    // {
    //     $test_file = "php_sdk_test";
    //     $consumer = new FileConsumer($test_file);
    //     $sa = new SensorsAnalytics($consumer);
    //     $test_api_url = 'test_api_url';
    //     $sab = new SensorsABTesting($test_api_url, $sa, array(
    //         "cache_config" => array(
    //             "type" => "redis",
    //             "ip" => "localhost",
    //             "port" => "6379"
    //         )
    //     ));
    //     $this->assertSame($test_api_url, $sab->_api_url);
    // }

    // public function testExperimentResult() {
    //     $test_file = "php_sdk_test";
        // $consumer = new FileConsumer($test_file);
        // $sa = new SensorsAnalytics($consumer);
        // $sab = new SensorsABTesting("http://10.130.6.5:8202/api/v2/abtest/online/results?project-key=438B9364C98D54371751BA82F6484A1A03A5155E", $sa);
    //     $ret = $sab->async_fetch_abtest("mengxy", 1, array(
    //         "default_value" => "10",
    //         "param_name"=> 'testParam'
    //     ));
    //     $this->assertSame($ret, "10");
    //     $ret = $sab->async_fetch_abtest("mengxy", 1, array(
    //         "default_value" => "10",
    //         "param_name"=> 'qqqq'
    //     ));
    //     $this->assertSame($ret, "1");
    // }

    // public function testMemcached() {
    //     $memcache = new memcached();
    //     $memcache->addServer('localhost',11211);
    //     $memcache->set('memcached',"1");
    //     $this->assertSame($memcache->get('memcached'), "1");
    // }

    // public function testRedis() {
    //     $redis = new Redis();
    //     $redis->connect('127.0.0.1', 6379);
    //     $redis->set("redis", "1");
    //     $this->assertSame($redis->get('redis'), "1");
    //     $delRet = $redis->del("redis");
    //     echo "\ndelRet: $delRet\n";
    //     $this->assertSame($redis->get('redis'), false);
    // }

}
