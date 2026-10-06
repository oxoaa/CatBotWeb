<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '票房排行') {
    $r = get('https://api.shanhe.kim/API/全网票房.php?type=text');
    $text = (string)$r->getBody();

    if (empty($text)) {
        $this->发送('文本', '获取失败');
        return;
    }

    $text = preg_replace('/【全网票房】\n?/', '', $text);
    $text = preg_replace('/\|?\s*数据来源：百度\s*/', '', $text);
    $text = trim($text);

    // 提取日期时间作为标题
    if (preg_match('/^([\d]{4}年[\d]{1,2}月[\d]{1,2}日\s*[\d:]+)/', $text, $m)) {
        $timeLine = $m[1];
        $rest = trim(substr($text, strlen($m[0])));
    } else {
        $timeLine = '';
        $rest = $text;
    }

    $md = '<@' . $this->用户ID . '>
**' . $timeLine . '**
```票房排行榜
' . $rest . '
```';

    $this->发送('md', null, $md);
}
