<?php

use function Swoole\Coroutine\Http\get;

$keys = [
    'FB6A29D268BDA98DB994F3B7F83877C8',
    'E78FCE853600D44F54EC6BB0FB98BB42',
    '862E75CBFBB0DE50796F9C8D682A5290',
    '84CB76EF78BA64849233D58A57587426',
];

if (preg_match('/^查信息\s*(\d+)?$/', $this->用户信息, $m)) {
    $qq = trim($m[1] ?? '');

    if (empty($qq)) {
        $this->发送('md', null, '请在后面输入QQ号
例如：<qqbot-cmd-input text="查信息" show="查信息"/>123456');
        return;
    }

    $key = $keys[array_rand($keys)];
    $url = 'https://api.s01s.cn/API/zcsj/?key=' . $key . '&qq=' . $qq;
    $r = get($url);
    $text = (string)$r->getBody();

    if (empty($text)) {
        $this->发送('文本', '查询失败');
        return;
    }

    $md = '<@' . $this->用户ID . '>
```
' . $text . '
```';

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    ['id' => '1', 'render_data' => ['label' => '猫妹交流群', 'style' => 1], 'action' => ['type' => 0, 'permission' => ['type' => 2], 'data' => 'https://qm.qq.com/q/KilBFgFV26']]
                ]
            ]
        ]
    ];

    $this->发送('md', null, $md, $键盘);
}
