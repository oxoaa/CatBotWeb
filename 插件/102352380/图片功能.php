<?php

if ($this->用户信息 == "图片功能") {

    $链接文件 = __DIR__ . '/../../api/txt/pixiv.txt';
    $链接列表 = array_values(array_filter(array_map('trim', file($链接文件, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))));
    $imgUrl = !empty($链接列表) ? $链接列表[array_rand($链接列表)] : '';

    $md = '![图片功能 #1242px #1863px](' . $imgUrl . ')';

    $键盘 = [
        "style" => ["font_size" => "small"],
        "rows" => [
            [
                "buttons" => [
                    [
                        "id" => "1",
                        "render_data" => ["label" => "二次元图", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "二次元图"]
                    ],
                    [
                        "id" => "2",
                        "render_data" => ["label" => "王者皮肤", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "王者皮肤"]
                    ],
                    [
                        "id" => "3",
                        "render_data" => ["label" => "七濑胡桃", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "七濑胡桃"]
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
