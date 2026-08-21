<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '漫剪视频') {
    $链接文件 = __DIR__ . '/../../api/txt/mjsp.txt';
    $链接列表 = array_values(array_filter(array_map('trim', file($链接文件, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))));

    if (!empty($链接列表)) {
        $videoUrl = $链接列表[array_rand($链接列表)];
        $this->发送('视频', $videoUrl);

        $md = ' ';

        $键盘 = [
            'style' => ['font_size' => 'small'],
            'rows' => [
                [
                    'buttons' => [
                        [
                            'id' => '1',
                            'render_data' => ['label' => '继续获取', 'style' => 1],
                            'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '漫剪视频']
                        ],
                        [
                            'id' => '2',
                            'render_data' => ['label' => '更多功能', 'style' => 1],
                            'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '菜单']
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
