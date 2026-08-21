<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '今日小猪') {
    $链接文件 = __DIR__ . '/../../api/txt/jrxz.txt';
    $链接列表 = array_values(array_filter(array_map('trim', file($链接文件, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))));

    if (!empty($链接列表)) {
        $line = $链接列表[crc32(date('Y-m-d') . $this->用户ID) % count($链接列表)];
        $parts = explode('|', $line);
        $id = $parts[0];
        $name = $parts[1];
        $desc = $parts[2];
        $analysis = $parts[3];
        $imgUrl = 'https://x.ocoa.cn/jrxz/' . $id . '.png';

        $md = '<@' . $this->用户ID . '>
你的今日小猪是：**' . $name . '**
' . $desc . '
![今日小猪 #256px #256px](' . $imgUrl . ')
> ' . $analysis;

        $键盘 = [
            'style' => ['font_size' => 'small'],
            'rows' => [
                [
                    'buttons' => [
                        [
                            'id' => '1',
                            'render_data' => ['label' => '继续获取', 'visited_label' => '', 'style' => 1],
                            'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '今日小猪', 'reply' => false, 'enter' => false]
                        ],
                        [
                            'id' => '2',
                            'render_data' => ['label' => '更多娱乐', 'visited_label' => '', 'style' => 1],
                            'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '娱乐功能', 'reply' => false, 'enter' => false]
                        ]
                    ]
                ],
                [
                    'buttons' => [
                        [
                            'id' => '99',
                            'render_data' => ['label' => '猫妹交流群', 'style' => 1],
                            'action' => ['type' => 0, 'permission' => ['type' => 2], 'data' => 'https://qm.qq.com/q/KilBFgFV26']
                        ]
                    ]
                ]
            ]
        ];

        $this->发送('md', null, $md, $键盘);
    }
}
