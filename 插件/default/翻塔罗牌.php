<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '翻塔罗牌') {
    $response = get('https://api.tangdouz.com/tarot.php');
    $text = (string)$response->getBody() ?? '';

    $text = str_replace('🔮塔罗牌🔮', '', $text);
    $text = str_replace('\r', "\n", $text);

    $img = '';
    if (preg_match('/±img=(.*?)±/', $text, $match)) {
        $img = $match[1];
        $text = str_replace($match[0], '', $text);
    }

    $md = '';
    if ($img) {
        $md = '![塔罗牌 #948px #1894px](' . $img . ')

' . trim($text);
    } else {
        $md = trim($text);
    }

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    [
                        'id' => '1',
                        'render_data' => ['label' => '继续获取', 'visited_label' => '', 'style' => 1],
                        'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '翻塔罗牌', 'reply' => false, 'enter' => false]
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
