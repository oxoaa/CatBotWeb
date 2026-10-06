<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '音乐功能') {
        $链接文件 = __DIR__ . '/../../api/txt/pixiv.txt';
    $链接列表 = array_values(array_filter(array_map('trim', file($链接文件, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))));
    $imgUrl = !empty($链接列表) ? $链接列表[array_rand($链接列表)] : '';
    $md = '![音乐功能 #1242px #1863px](' . $imgUrl . ')';

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    ['id' => '1', 'render_data' => ['label' => '哈基米', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '哈基米']],
                    ['id' => '3', 'render_data' => ['label' => '王者语音', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '王者语音']]
                ]
            ],
            [
                'buttons' => [
                    ['id' => '2', 'render_data' => ['label' => '古风国风', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '古风国风']],
                    ['id' => '3', 'render_data' => ['label' => '欧美音乐', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '欧美音乐']]
                ]
            ],
            [
                'buttons' => [
                    ['id' => '4', 'render_data' => ['label' => '旋律潮流', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '旋律潮流']],
                    ['id' => '5', 'render_data' => ['label' => '伤感音乐', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '伤感音乐']]
                ]
            ],
            [
                'buttons' => [
                    ['id' => '6', 'render_data' => ['label' => '中文歌曲', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '中文歌曲']],
                    ['id' => '7', 'render_data' => ['label' => '日韩歌曲', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '日韩歌曲']]
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

if (in_array($this->用户信息, ['古风国风', '欧美音乐', '日韩歌曲', '伤感音乐', '旋律潮流', '中文歌曲'])) {
    $cate = $this->用户信息;

    $数量 = [
        '古风国风' => 39,
        '欧美音乐' => 22,
        '日韩歌曲' => 14,
        '伤感音乐' => 27,
        '旋律潮流' => 80,
        '中文歌曲' => 49,
    ];

    $总数 = $数量[$cate] ?? 0;
    $url = 'http://x.ocoa.cn/' . rawurlencode($cate) . '/' . rand(1, max(1, $总数)) . '.mp3';

    if ($总数 > 0) {
        $this->发送('语音', $url);

            $链接文件 = __DIR__ . '/../../api/txt/pixiv.txt';
    $链接列表 = array_values(array_filter(array_map('trim', file($链接文件, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))));
    $imgUrl = !empty($链接列表) ? $链接列表[array_rand($链接列表)] : '';
    $md = '![音乐功能 #1242px #1863px](' . $imgUrl . ')';

        $键盘 = [
        'style' => ['font_size' => 'small'],
            'rows' => [
                [
                    'buttons' => [
                        ['id' => '1', 'render_data' => ['label' => '继续获取', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => $cate]],
                        ['id' => '2', 'render_data' => ['label' => '更多音乐', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '音乐功能']]
                    ]
                ],
                [
                    'buttons' => [
                        ['id' => '99', 'render_data' => ['label' => '猫妹交流群', 'style' => 1], 'action' => ['type' => 0, 'permission' => ['type' => 2], 'data' => 'https://qm.qq.com/q/KilBFgFV26']]
                    ]
                ]
            ]
        ];

        $this->发送('md', null, $md, $键盘);
    }
}

if ($this->用户信息 == '哈基米') {
    $url = 'http://x.ocoa.cn/hjm/' . rand(1, 52) . '.amr';
    $this->发送('语音', $url);

        $链接文件 = __DIR__ . '/../../api/txt/pixiv.txt';
    $链接列表 = array_values(array_filter(array_map('trim', file($链接文件, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))));
    $imgUrl = !empty($链接列表) ? $链接列表[array_rand($链接列表)] : '';
    $md = '![音乐功能 #1242px #1863px](' . $imgUrl . ')';

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    ['id' => '1', 'render_data' => ['label' => '继续获取', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '哈基米']],
                    ['id' => '2', 'render_data' => ['label' => '更多音乐', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '音乐功能']]
                ]
            ],
            [
                'buttons' => [
                    ['id' => '99', 'render_data' => ['label' => '猫妹交流群', 'style' => 1], 'action' => ['type' => 0, 'permission' => ['type' => 2], 'data' => 'https://qm.qq.com/q/KilBFgFV26']]
                ]
            ]
        ]
    ];

    $this->发送('md', null, $md, $键盘);
}
