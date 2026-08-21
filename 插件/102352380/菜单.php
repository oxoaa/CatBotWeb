<?php

if ($this->用户信息 == "菜单") {

    // 记录用户ID
    $用户列表 = $this->数据库("读", "菜单/用户列表") ?? [];
    if (!in_array($this->用户ID, $用户列表)) {
        $用户列表[] = $this->用户ID;
        $this->数据库("写", "菜单/用户列表", $用户列表);
    }

    // 记录群号（群聊时）
    if (in_array($this->事件类型, ["GROUP_AT_MESSAGE_CREATE", "GROUP_MESSAGE_CREATE"])) {
        $群列表 = $this->数据库("读", "菜单/群列表") ?? [];
        if (!in_array($this->来源ID, $群列表)) {
            $群列表[] = $this->来源ID;
            $this->数据库("写", "菜单/群列表", $群列表);
        }
    }

    $链接文件 = __DIR__ . '/../../api/txt/pixiv.txt';
    $链接列表 = array_values(array_filter(array_map('trim', file($链接文件, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))));
    $imgUrl = !empty($链接列表) ? $链接列表[array_rand($链接列表)] : '';
    $md = '![菜单 #1242px #1863px](' . $imgUrl . ')
><qqbot-cmd-input text="天气查询" show="天气查询"/> | <qqbot-cmd-input text="漫剪视频" show="漫剪视频"/>
<qqbot-cmd-input text="文字找茬" show="文字找茬"/> | <qqbot-cmd-input text="QQ音乐榜" show="QQ音乐榜"/>
';

    $键盘 = [
        "style" => ["font_size" => "small"],
        "rows" => [
            [
                "buttons" => [
                    [
                        "id" => "1",
                        "render_data" => ["label" => "图片功能", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "图片功能"]
                    ],
                    [
                        "id" => "2",
                        "render_data" => ["label" => "娱乐功能", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "娱乐功能"]
                    ]
                ]
            ],
            [
                "buttons" => [
                    [
                        "id" => "3",
                        "render_data" => ["label" => "音乐功能", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "音乐功能"]
                    ],
                    [
                        "id" => "4",
                        "render_data" => ["label" => "点歌功能", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "点歌功能"]
                    ],
                ]
            ],
            [
                "buttons" => [
                    [
                        "id" => "7",
                        "render_data" => ["label" => "知鱼小栈", "style" => 1],
                        "action" => ["type" => 0, "permission" => ["type" => 2], "data" => "https://m.q.qq.com/a/s/6383ae34f73f898c3dcde9e1e30793be"]
                    ],
                    [
                        "id" => "8",
                        "render_data" => ["label" => "猫粮投喂", "style" => 1],
                        "action" => ["type" => 0, "permission" => ["type" => 2], "data" => "https://www.yuque.com/yuqueyonghuniy4cb/kka4vu/cmh27ic4i2debiss"]
                    ]
                ]
            ],

        ]
    ];

    $this->发送('md', null, $md, $键盘);
}
