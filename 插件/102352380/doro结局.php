<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == 'doro结局') {
    $url = 'http://x.ocoa.cn/doro/' . rand(1, 32) . '.jpg';
    $md = '![doro结局 #850px #1197px](' . $url . ')';

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    [
                        'id' => '1',
                        'render_data' => ['label' => '继续获取', 'visited_label' => '', 'style' => 1],
                        'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => 'doro结局', 'reply' => false, 'enter' => false]
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
