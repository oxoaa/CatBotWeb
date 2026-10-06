<?php

if ($this->用户信息 == "菜单") {

    $用户列表 = $this->数据库("读", "菜单/用户列表") ?? [];
    if (!in_array($this->用户ID, $用户列表)) {
        $用户列表[] = $this->用户ID;
        $this->数据库("写", "菜单/用户列表", $用户列表);
    }

    if (in_array($this->事件类型, ["GROUP_AT_MESSAGE_CREATE", "GROUP_MESSAGE_CREATE"])) {
        $群列表 = $this->数据库("读", "菜单/群列表") ?? [];
        if (!in_array($this->来源ID, $群列表)) {
            $群列表[] = $this->来源ID;
            $this->数据库("写", "菜单/群列表", $群列表);
        }
    }

    $imgUrl = 'https://download.nature.qq.com/SnsShare/yxapi/2026-08-24/23:06:02/0ff9a753.png';

    $md = '![菜单 #1024px #1536px](' . $imgUrl . ')';

    $功能列表 = [
        'QQ点歌', 'doro结局', '查靓号', '网易点歌', '今日小猪', '今天吃啥',
        '今日老婆', '今日运势', '汽水点歌', '酷狗点歌', '二次元图', '漫剪视频',
        '天气查询', '王者语音', '王者皮肤', '文字找茬', '票房排行', '翻塔罗牌',
        '网易热评', '七濑胡桃', 'QQ音乐榜', '星座运势', '随机超能力', '三角洲每日密码'
    ];

    $rows = [];
    for ($i = 0; $i < count($功能列表); $i += 6) {
        $row = [];
        for ($j = $i; $j < $i + 6 && $j < count($功能列表); $j++) {
            $row[] = [
                "id" => (string)($j + 1),
                "render_data" => ["label" => (string)($j + 1), "style" => 1],
                "action" => ["type" => 2, "permission" => ["type" => 2], "data" => $功能列表[$j], "reply" => false, "enter" => false]
            ];
        }
        $rows[] = ["buttons" => $row];
    }

    $rows[] = [
        "buttons" => [
            ["id" => "29", "render_data" => ["label" => "知鱼小栈", "style" => 1], "action" => ["type" => 0, "permission" => ["type" => 2], "data" => "https://m.q.qq.com/a/s/6383ae34f73f898c3dcde9e1e30793be"]],
            ["id" => "30", "render_data" => ["label" => "猫粮投喂", "style" => 1], "action" => ["type" => 0, "permission" => ["type" => 2], "data" => "https://www.yuque.com/yuqueyonghuniy4cb/kka4vu/cmh27ic4i2debiss"]]
        ]
    ];

    $键盘 = [
        "style" => ["font_size" => "small"],
        "rows" => $rows
    ];

    $this->发送('md', null, $md, $键盘);
}
