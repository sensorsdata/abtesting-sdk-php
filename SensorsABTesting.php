<?php

define('SENSORS_ABTESTING_SDK_VERSION', '0.0.1');
require 'vendor/autoload.php';

require_once(__DIR__ . "/lib/LRUCache.php");
require_once(__DIR__."/lib/cache.php");

class SensorsABTesting {

    /*
     * SensorsAnalytics 埋点 SDK 实例
     */
    public $_sa;

    /*
     * 项目数据接收地址
     */
    public $_api_url;

    /*
     * 是否开启自动上报事件	
     */
    private $_enable_event_cache = true;

    /*
     * 请求超时时间
     */
    private $_request_timeout = 3000;

    /*
     * 实验结果缓存
     */
    private $_experiments_cache;

    /*
     * $ABTestTrigger事件缓存
     */
    private $_events_cache;

    /*
     * $lib_plugin_version 缓存
     */
    private $_lib_plugin_version_cache;

    /**
     * @param string $api_url 项目数据接收地址	
     * @param object $sa SensorsAnalytics 埋点 SDK 实例
     * @param object $init_params 
     */
    public function __construct($api_url, $sa, $cache_config = null) {
        if (is_string($api_url) && !empty($api_url)) {
            $this->_api_url = $api_url;
        } else {
            throw new Exception("api_url invalid, please set string");
        }

        if ($sa instanceof SensorsAnalytics) {
            $this->_sa = $sa;
        } else {
            throw new Exception("\$sa is not an instance of SensorsAnalytics");
        }

        // connect cache service
        if (isset($cache_config)) {
            if (!is_array($cache_config)) {
                throw new Exception("cache_config must be an array");
            }
            if (!isset($cache_config['enable_event_cache'])) {
                $cache_config['enable_event_cache'] = true;
            } 
            

            if (isset($cache_config['type'])) {
                if (!($cache_config['type'] == 'redis' || $cache_config['type'] == 'memcached')) {
                    throw new Exception("Invalid cache server type, only redis/memcached supported");
                }
            }

            if (isset($cache_config['host']) && isset($cache_config['port'])) {
                $experiment_cache_size = 4096;
                if (isset($cache_config['experiment_cache_size']) && intval($cache_config['experiment_cache_size']) > 0) {
                    $experiment_cache_size = intval($cache_config['experiment_cache_size']);
                }
                $experiment_cache_time = 86400;
                if (isset($cache_config['experiment_cache_time'])
                    && intval($cache_config['experiment_cache_time']) > 0
                    && intval($cache_config['experiment_cache_time']) < $experiment_cache_time) {
                    $experiment_cache_time = intval($cache_config['experiment_cache_time']);
                }

                try {
                    $this->_experiments_cache = new LRUCache(
                        $cache_config['type'], 
                        $cache_config['host'], 
                        $cache_config['port'], 
                        isset($cache_config['auth']) ? $cache_config['auth'] : null , 
                        "experiment",
                        $experiment_cache_size,
                        $experiment_cache_time
                    );
                    if (!isset($this->_experiments_cache)) {
                        $this->print_log("Experiment cache server can not connect. fast_fetch will fallback to async_fetch");
                    }
                } catch (Exception $e) {
                    $this->print_log("Experiment cache server can not connect. fast_fetch will fallback to async_fetch");
                }
            }


            if ($cache_config['enable_event_cache']) {
                $event_cache_size = 4096;
                if (isset($cache_config['event_cache_size']) && intval($cache_config['event_cache_size']) > 0) {
                    $event_cache_size = intval($cache_config['event_cache_size']);
                }
                $event_cache_time = 86400;
                if (isset($cache_config['event_cache_time'])
                    && intval($cache_config['event_cache_time']) > 0
                    && intval($cache_config['event_cache_time']) < $event_cache_time) {
                    $event_cache_time = intval($cache_config['event_cache_time']);
                }

                try {
                    $this->_events_cache = new LRUCache(
                        $cache_config['type'], 
                        $cache_config['host'], 
                        $cache_config['port'], 
                        isset($cache_config['auth']) ? $cache_config['auth'] : null , 
                        "event",
                        $event_cache_size,
                        $event_cache_time
                    );
                    if (!isset($this->_events_cache)) {
                        $this->print_log("Event cache server can not connect. Event cache disabled");
                        $this->_enable_event_cache = false;
                    }
                } catch (\Throwable $th) {
                    $this->print_log("Event cache server can not connect. Event cache disabled");
                    $this->_enable_event_cache = false;
                }
            } else {
                $this->_enable_event_cache = false;
            }

            try {
                $this->_lib_plugin_version_cache = new Cache(
                    $cache_config['type'], 
                    $cache_config['host'], 
                    $cache_config['port'], 
                    isset($cache_config['auth']) ? $cache_config['auth'] : null , 
                );
            } catch (\Throwable $th) {
            }
        }

        return $this;
    }

    /**
     * distinct_id: 用户标识（必填）
     * is_login_id: 是否是登录 ID（必填）
     * request_param: 请求参数（必填）
     *  {
     *      default_value: 默认值（必填）
     *      param_name: 试验变量名（必填）
     *      enable_auto_track_event: 是否自动触发 A/B Testing 埋点事件
     *      $properties: 请求试验的分流筛选属性
     *      timeout_milliseconds: 网络请求超时时间，单位 ms，默认值3000
     *  }
     */
    public function async_fetch_abtest($distinct_id, $is_login_id, $request_params) {
        return $this->fetch_ab_test($distinct_id, $is_login_id, $request_params, false);
    }

    /**
     * distinct_id: 用户标识（必填）
     * is_login_id: 是否是登录 ID（必填）
     * request_param: 请求参数（必填）
     *  {
     *      default_value: 默认值（必填）
     *      param_name: 试验变量名（必填）
     *      enable_auto_track_event: 是否自动触发 A/B Testing 埋点事件
     *      timeout_milliseconds: 网络请求超时时间，单位 ms，默认值3000
     *  }
     */
    public function fast_fetch_abtest($distinct_id, $is_login_id, $request_params) {
        return $this->fetch_ab_test($distinct_id, $is_login_id, $request_params, true);
    }

    public function fetch_ab_test($distinct_id, $is_login_id, $request_params, $enable_cache) {
        if (!isset($request_params['default_value'])) {
            throw new Exception("default_value must be set in \$request_params");
        }
        if (!isset($request_params['value_type'])) {
            throw new Exception("value_type must be set in \$request_params");
        }
        if (!is_string($request_params['value_type'])) {
            throw new Exception("value_type must be string");
        }
        if (!$this->verify_type($request_params['default_value'], $request_params['value_type'])) {
            throw new Exception("default_value should match value_type");
        }
        if (!isset($request_params['param_name'])) {
            throw new Exception("param_name must be set in \$request_params");
        }
        if (!is_string($request_params['param_name'])) {
            throw new Exception("param_name must be string");
        }
        if (!is_string($distinct_id)) {
            throw new Exception("\$distinct_id must be string");
        }
        if (!is_bool($is_login_id)) {
            throw new Exception("\$is_login_id must be boolean");
        }

        $enable_auto_track_event = true;
        if (isset($request_params['enable_auto_track_event']) && is_bool($request_params['enable_auto_track_event'])) {
            $enable_auto_track_event = $request_params['enable_auto_track_event'];
        }

        if ($enable_cache && isset($this->_experiments_cache)) {
            try {
                $experiments = $this->_experiments_cache->get($distinct_id.$is_login_id);
                if (empty($experiments)) {
                    $experiments = $this->_fetch_experiment_result($distinct_id, $is_login_id, $request_params);
                    $this->_experiments_cache->put($distinct_id.$is_login_id, $experiments);
                }
            } catch(Exception $e) {
                $this->print_log("Cache server connection lost. Fetch experiment result from server directly");
                $experiments = $this->_fetch_experiment_result($distinct_id, $is_login_id, $request_params);
            }
        } else {
            $experiments = $this->_fetch_experiment_result($distinct_id, $is_login_id, $request_params);
        }
        $experiment_result = $this->_convert_experiments($experiments, $distinct_id, $is_login_id, $request_params['param_name'], $request_params['value_type'], $request_params['default_value']);

        if ($enable_auto_track_event) {
            $this->track_abtesttrigger($experiment_result);
        }

        return $experiment_result;
    }

    public function track_abtesttrigger($experiment_result, $custom_properties = []) {
        try {
            if (isset($experiment_result['is_white_list']) && $experiment_result['is_white_list']) {
                return;
            }

            if (isset($experiment_result['abtest_experiment_id'])) {
                $distinct_id = $experiment_result['distinct_id'];
                $is_login_id = $experiment_result['is_login_id'];
                
                $event_cache = false;
                if ($this->_enable_event_cache && $this->_events_cache) {
                    $event_cache_key = $distinct_id.$is_login_id.$experiment_result['abtest_experiment_id'];
                    try {
                        $event_cache = $this->_events_cache->get($event_cache_key);
                        $this->_events_cache->put($event_cache_key, 1);
                    } catch(Exception $e) {
                        $this->print_log("Cache server connection lost. Event cache disabled");
                    }
                }

                if (!$event_cache) {
                    $properties = array(
                        '$abtest_experiment_id' => $experiment_result['abtest_experiment_id'],
                        '$abtest_experiment_group_id' => $experiment_result['abtest_experiment_group_id'],
                    );

                    if ($this->_lib_plugin_version_cache) {
                        try {
                            $lib_cache_key = "has_lib_plugin_version";
                            if (!$this->_lib_plugin_version_cache->get($lib_cache_key)) {
                                $properties = array_merge($properties, [
                                    '$lib_plugin_version' => ["php_abtesting:".SENSORS_ABTESTING_SDK_VERSION]
                                ]);
                                $expire = (new DateTime())->getTimestamp() + 86400;
                                $this->_lib_plugin_version_cache->set($lib_cache_key, 1, $expire);;
                            }
                        } catch(Exception $e) { }
                    } else {
                        $properties = array_merge($properties, [
                            '$lib_plugin_version' => ["php_abtesting:".SENSORS_ABTESTING_SDK_VERSION]
                        ]);
                    }

                    $properties = array_merge($properties, $custom_properties);
                    $this->_sa->track(
                        $distinct_id, 
                        (boolean)$is_login_id, 
                        '$ABTestTrigger', 
                        $properties
                    );
                }
            }

        } catch (Exception $e) {
            $this->print_log("Track \$abtestTrigger failed: ".$e->getMessage());
        }
    }

    public function _fetch_experiment_result($distinct_id, $is_login_id, $request_params) {
        try {
            $params = array(
                "platform" => "php",
                "abtest_lib_version" => SENSORS_ABTESTING_SDK_VERSION,
                "properties" => new stdClass()
            );

            $params = array_merge($params, $is_login_id ? 
                array("login_id" => $distinct_id) :
                array("anonymous_id" => $distinct_id) 
            );

            $request_timeout = 3000;
            if (isset($request_params['timeout_milliseconds']) && $request_params['timeout_milliseconds'] > 0) {
                $request_timeout = $request_params['timeout_milliseconds'];
            }
            $ret = $this->_do_request($params, $request_timeout);
            return json_decode($ret['ret_content']) -> results; 
        } catch (Exception $e) {
            $this->print_log("Fetch experiment result failed: ".$e->getMessage());
            return [];
        }
    }

    public function _convert_experiments($experiments, $distinct_id, $is_login_id, $param_name, $param_type, $default_value) {
        foreach ($experiments as $key => $experiment) {
            foreach ($experiment->variables as $key => $variable) {
                if ($variable->name == $param_name) {
                    $data_type = $variable->type;
                    if ($data_type != $param_type) {
                        continue;
                    }
                    $value = $variable->value;
                    switch ($data_type) {
                        case 'STRING':
                            $value = strval($value);
                            break;
                        case 'INTEGER':
                            $value = intval($value);
                            break;
                        case 'JSON':
                            $value = json_decode($value);
                            break;
                        case 'BOOLEAN':
                            $value = boolval($value);
                            break;
                        default:
                            $value = null;
                            break;
                    }

                    if ($value) {
                        return array(
                            "distinct_id" => $distinct_id,
                            "is_login_id" => $is_login_id,
                            "abtest_experiment_id" => $experiment->abtest_experiment_id,
                            "abtest_experiment_group_id" => $experiment->abtest_experiment_group_id,
                            "is_white_list" => $experiment->is_white_list,
                            "is_control_group" => $experiment->is_control_group,
                            "value" => $value
                        );
                    }
                }
            }
        }

        return array(
            "distinct_id" => $distinct_id,
            "is_login_id" => $is_login_id,
            "value" => $default_value
        );
    }

    /**
     * 发送数据包给远程服务器。
     *
     * @param array $data
     * @return array
     * @throws SensorsAnalyticsDebugException
     */
    protected function _do_request($data, $request_timeout) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $this->_api_url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $request_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $request_timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_USERAGENT, "PHP ABTesting SDK");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));


        //judge https
        $pos = strpos($this->_api_url, "https");
        if ($pos === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        
        $http_response_header = curl_exec($ch);
        if (!$http_response_header) {
            throw new SensorsAnalyticsDebugException(
                   "Failed to connect to SensorsAnalytics A/B Testing. [error='".curl_error($ch)."']");
        }

        $result = array(
            "ret_content" => $http_response_header,
            "ret_code" => curl_getinfo($ch, CURLINFO_HTTP_CODE)
        );
        curl_close($ch);

        return $result;
    }

    private function verify_type($value, $type) {
        switch ($type) {
            case 'INTEGER':
                if (is_numeric($value)) {
                    return true;
                }
                break;
            case 'STRING':
                if (is_string($value)) {
                    return true;
                }
                break;
            case 'JSON':
                if (is_object($value)) {
                    return true;
                }
                break;
            case 'BOOLEAN':
                if (is_bool($value)) {
                    return true;
                }
                break;
            default:
                throw new Exception("invalid value_type, value_type must be in 'INTEGER' | 'STRING' | 'JSON' | 'BOOLEAN'");
                return false;
        }
    }

    private function print_log($msg) {
        $time = date(DATE_W3C);
        echo $time.": ".$msg;
    }
}
