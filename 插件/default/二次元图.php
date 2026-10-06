<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '二次元图') {
    $链接文件 = __DIR__ . '/../../api/txt/pixiv.txt';
    $链接列表 = array_values(array_filter(array_map('trim', file($链接文件, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))));

    if (!empty($链接列表)) {
        $imgUrl = $链接列表[array_rand($链接列表)];
        $md = '![二次元图 #1242px #1863px](' . $imgUrl . ')';

        $键盘 = [
            'style' => ['font_size' => 'small'],
            'rows' => [
                [
                    'buttons' => [
                        [
                            'id' => '1',
                            'render_data' => ['label' => '继续获取', 'style' => 1],
                            'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '二次元图']
                        ],
                        [
                            'id' => '2',
                            'render_data' => ['label' => '更多图片', 'style' => 1],
                            'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '图片功能']
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
