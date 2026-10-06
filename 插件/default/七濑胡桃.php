<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '七濑胡桃') {
    $response = get('http://api.ocoa.cn/api/mcj.php?type=json');
    $data = json_decode((string)$response->getBody(), true);

    if (!empty($data['url'])) {
        $url = $data['url'];
        $md = '![七濑胡桃 #1920px #1080px](' . $url . ')';

        $键盘 = [
            'style' => ['font_size' => 'small'],
            'rows' => [
                [
                    'buttons' => [
                        [
                            'id' => '1',
                            'render_data' => ['label' => '继续获取', 'visited_label' => '', 'style' => 1],
                            'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '七濑胡桃', 'reply' => false, 'enter' => false]
                        ],
                        [
                            'id' => '2',
                            'render_data' => ['label' => '更多图片', 'visited_label' => '', 'style' => 1],
                            'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '图片功能', 'reply' => false, 'enter' => false]
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
