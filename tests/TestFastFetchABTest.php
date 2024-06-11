<?php
require 'vendor/autoload.php';
require_once (__DIR__ . '/SensorsABTesting.php');

# 从神策分析配置页面中获取的数据接收的 URL
# $SA_SERVER_URL = 'YOUR_SERVER_URL';
# 获取分流试验请求地址
// $ABTesting_API_URL = 'https://abtest-tx-beijing-01.abtest-trial.sensorsdata.cn/api/v2/abtest/online/results?project-key=B0103D97DEC0A8810D948AFFBAD7A86B3DBF98E8';
$ABTesting_API_URL = 'http://10.129.26.155:8202/api/v2/abtest/online/results?project-key=3C76483241497D19B72A7995CC5FD1B5081F0BC0';
# 初始化一个 Consumer，用于数据发送
# BatchConsumer 是同步发送数据，因此不要在任何线上的服务中使用此 Consumer
$consumer = new FileConsumer("sa.log." . date('Y-m-d'));
# 使用 Consumer 来构造 SensorsAnalytics 对象
$sa = new SensorsAnalytics($consumer);
# 支持在构造SensorsAnalytics对象时指定project, 后续所有通过这个SensorsAnalytics对象发送的数据都将发往这个project
# $sa = new SensorsAnalytics($consumer, "project_name");
# 初始化 PHP AB 功能
$sab = new SensorsABTesting($ABTesting_API_URL, $sa, [
    "type" => "redis",
	"host" => "127.0.0.1",
	"port" => "6379",
	"pass" => "",
    "experiment_cache_size" => 50,
    "event_cache_size" => 50
]);
// echo "成功初始化，延迟30s进行分流请求";
// sleep(30);
// $distinct_id = "123456"; // 具体的用户 ID 标识
// $is_login_id = true; // 当前用户是否是登录 ID
// $experiment_result = $sab->fast_fetch_abtest($distinct_id, $is_login_id, [
//     "param_name" => 'test',
//     "default_value" => 0, //默认值，必填
//     "value_type" => 'INTEGER', //试验变量类型，必填
//     "enable_auto_track_event" => true, // true 表示自动触发 A/B 测试（$ABTestTrigger）事件，用于后续分析试验效果，计算置信区间等
// ]);
// # 打印试验结果
// echo "123456试验分流结果为：" . $experiment_result['value'];
$experiment_result = $sab->fast_fetch_abtest('1', true, [
    "param_name" => 'test',
    "default_value" => 0, //默认值，必填
    "value_type" => 'INTEGER', //试验变量类型，必填
    "experiments_cache" => true,
    "enable_auto_track_event" => true // true 表示自动触发 A/B 测试（$ABTestTrigger）事件，用于后续分析试验效果，计算置信区间等
]);



?>