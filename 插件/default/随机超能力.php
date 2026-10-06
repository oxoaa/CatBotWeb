<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '随机超能力') {
    $数据文件 = __DIR__ . '/../../api/cnl.json';
    $power = '未知超能力';
    $disadvantage = '未知副作用';

    if (is_file($数据文件) && is_readable($数据文件)) {
        $json = file_get_contents($数据文件);
        $列表 = json_decode($json, true);

        if (is_array($列表) && !empty($列表)) {
            $随机项 = $列表[array_rand($列表)];
            $power = $随机项['power'] ?? $power;
            $disadvantage = $随机项['but'] ?? $disadvantage;
        }
    }

    $md = '你的超能力是：**' . $power . '**

但是副作用是：**' . $disadvantage . '**';

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    [
                        'id' => '1',
                        'render_data' => ['label' => '继续获取', 'visited_label' => '', 'style' => 1],
                        'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '随机超能力', 'reply' => false, 'enter' => false]
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
