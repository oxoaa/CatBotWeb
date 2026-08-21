<?php

if ($this->用户信息 == "娱乐功能") {

    $链接文件 = __DIR__ . '/../../api/txt/pixiv.txt';
    $链接列表 = array_values(array_filter(array_map('trim', file($链接文件, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))));
    $imgUrl = !empty($链接列表) ? $链接列表[array_rand($链接列表)] : '';
    $md = '![娱乐功能 #1242px #1863px](' . $imgUrl . ')';

    $键盘 = [
        "style" => ["font_size" => "small"],
        "rows" => [
            [
                "buttons" => [
                    [
                        "id" => "1",
                        "render_data" => ["label" => "翻塔罗牌", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "翻塔罗牌", "reply" => false, "enter" => false]
                    ],
                    [
                        "id" => "2",
                        "render_data" => ["label" => "今日运势", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "今日运势", "reply" => false, "enter" => false]
                    ],
                    [
                        "id" => "3",
                        "render_data" => ["label" => "今天吃啥", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "今天吃啥", "reply" => false, "enter" => false]
                    ]
                ]
            ],
            [
                "buttons" => [
                    [
                        "id" => "4",
                        "render_data" => ["label" => "今日老婆", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "今日老婆", "reply" => false, "enter" => false]
                    ],
                    [
                        "id" => "5",
                        "render_data" => ["label" => "Doro结局", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "doro结局", "reply" => false, "enter" => false]
                    ],
                    [
                        "id" => "6",
                        "render_data" => ["label" => "随机超能力", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "随机超能力", "reply" => false, "enter" => false]
                    ]
                ]
            ],
            [
                "buttons" => [
                    [
                        "id" => "7",
                        "render_data" => ["label" => "今日小猪", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "今日小猪", "reply" => false, "enter" => false]
                    ],
                    [
                        "id" => "8",
                        "render_data" => ["label" => "星座运势", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "星座运势", "reply" => false, "enter" => false]
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
