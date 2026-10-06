<?php

if ($this->用户信息 == "点歌功能") {

        $链接文件 = __DIR__ . '/../../api/txt/pixiv.txt';
    $链接列表 = array_values(array_filter(array_map('trim', file($链接文件, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))));
    $imgUrl = !empty($链接列表) ? $链接列表[array_rand($链接列表)] : '';
    $md = '![点歌功能 #1242px #1863px](' . $imgUrl . ')';

    $键盘 = [
        "style" => ["font_size" => "small"],
        "rows" => [
            [
                "buttons" => [
                    [
                        "id" => "1",
                        "render_data" => ["label" => "QQ点歌", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "QQ点歌", "reply" => false, "enter" => false]
                    ],
                    [
                        "id" => "2",
                        "render_data" => ["label" => "网易点歌", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "网易点歌", "reply" => false, "enter" => false]
                    ]
                ]
            ],
            [
                "buttons" => [
                    [
                        "id" => "3",
                        "render_data" => ["label" => "酷狗点歌", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "酷狗点歌", "reply" => false, "enter" => false]
                    ],
                    [
                        "id" => "4",
                        "render_data" => ["label" => "汽水点歌", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "汽水点歌", "reply" => false, "enter" => false]
                    ]
                ]
            ],
            [
                "buttons" => [
                    [
                        "id" => "99",
                        "render_data" => ["label" => "猫妹交流群", "style" => 1],
                        "action" => ["type" => 0, "permission" => ["type" => 2], "data" => "https://qm.qq.com/q/KilBFgFV26"]
                    ]
                ]
            ]
        ]
    ];

    $this->发送('md', null, $md, $键盘);
}
