<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '今日运势') {
    $imgUrl = 'https://api.tangdouz.com/wz/luck.php?theme=&return=/?' . crc32(date('Y-m-d') . $this->用户ID);
    $md = '![今日运势 #480px #480px](' . $imgUrl . ')';

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    [
                        'id' => '1',
                        'render_data' => ['label' => '继续获取', 'visited_label' => '', 'style' => 1],
                        'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '今日运势', 'reply' => false, 'enter' => false]
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
