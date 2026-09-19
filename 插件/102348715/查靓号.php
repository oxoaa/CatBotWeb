<?php

if (preg_match('/^查靓号\s*(.*)$/', $this->用户信息 ?? '', $m)) {
    $qq = trim($m[1] ?? '');

    if (empty($qq)) {
        $this->发送('md', null, '请在后面输入QQ号
例如：<qqbot-cmd-input text="查靓号" show="查靓号"/>123456');
        return;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://api.avak.cn/qqbuy/",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "qq=" . $qq,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $res = trim($res);
    $res = preg_replace('/^\xEF\xBB\xBF/', '', $res);
    preg_match('/\{.*\}/s', $res, $match);

    $data = json_decode($match[0] ?? '', true);

    if (empty($data) || empty($data['qq'])) {
        $this->发送('文本', '查询失败，请检查QQ号');
        return;
    }

    $md = '<@' . $this->用户ID . '>
```
QQ：' . ($data['qq'] ?? '') . '
状态：' . ($data['condition'] ?? '') . '
类型：' . ($data['type'] ?? '') . '
会员：' . ($data['vip'] ?? '') . '
```';

    $this->发送('md', null, $md);
}