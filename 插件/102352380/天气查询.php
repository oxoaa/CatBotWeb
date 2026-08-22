<?php

use function Swoole\Coroutine\Http\get;

if (preg_match('/^天气查询\s*(.*)$/', $this->用户信息, $match)) {
    $city = trim($match[1] ?? '');

    if (empty($city)) {
        $this->发送('文本', '请在指令后面输入城市名称
例如：天气查询 北京');
        return;
    }

    $url = 'https://api.shanhe.kim/API/天气.php?city=' . urlencode($city) . '&type=text';
    $response = get($url);
    $text = (string)$response->getBody();

    if (empty($text)) {
        $this->发送('文本', '查询失败，请检查城市名称');
        return;
    }

    // 截取到更新时间行（含）为止
    if (preg_match('/^(.*更新时间[^\n]*)/s', $text, $m)) {
        $text = $m[1];
    }

    $md = '<@' . $this->用户ID . '>
' . $text;

    $this->发送('md', null, $md);
}
