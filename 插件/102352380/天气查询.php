<?php

use function Swoole\Coroutine\Http\get;

if (preg_match('/^天气查询\s*(.*)$/', $this->用户信息, $match)) {
    $city = trim($match[1] ?? '');

    if (empty($city)) {
        $this->发送('文本', '请在指令后面输入城市名称
例如：天气查询 北京');
        return;
    }

    $url = 'http://cyapi.top/API/weather.php?city=' . urlencode($city) . '&n=1&type=text&apikey=7c8c8f084709fcb51f3a0c867f1363ff9d71e0650157bcf405cb3539472372bd';
    $response = get($url);
    $text = (string)$response->getBody();

    if (empty($text)) {
        $this->发送('文本', '查询失败，请检查城市名称');
        return;
    }

    // 去掉详情页链接行
    $text = preg_replace('/【城市信息】\n.*?详情页:.*?\n/s', "【城市信息】\n城市: $city\n", $text);

    $this->发送('md', null, $text);
}
