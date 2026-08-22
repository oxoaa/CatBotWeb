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

    $imgUrl = 'https://download.nature.qq.com/SnsShare/yxapi/2026-08-22/18:42:31/031e1447.png';

    $md = '![菜单 #1672px #941px](' . $imgUrl . ')
**🎵 音乐专区**
🎵 <qqbot-cmd-input text="QQ点歌" show="QQ点歌"/> | 🎶 <qqbot-cmd-input text="网易点歌" show="网易点歌"/>
🥤 <qqbot-cmd-input text="汽水点歌" show="汽水点歌"/> | 🏆 <qqbot-cmd-input text="QQ音乐榜" show="QQ音乐榜"/>
🎼 <qqbot-cmd-input text="音乐功能" show="音乐功能"/> | 🎤 <qqbot-cmd-input text="点歌功能" show="点歌功能"/>
💬 <qqbot-cmd-input text="网易热评" show="网易热评"/>

---

**📅 每日运势**
🌟 <qqbot-cmd-input text="今日运势" show="今日运势"/> | ♈ <qqbot-cmd-input text="星座运势" show="星座运势"/>
👰 <qqbot-cmd-input text="今日老婆" show="今日老婆"/> | 🐷 <qqbot-cmd-input text="今日小猪" show="今日小猪"/>
🍽 <qqbot-cmd-input text="今天吃啥" show="今天吃啥"/> | 🎭 <qqbot-cmd-input text="doro结局" show="doro结局"/>

---

**🖼 图片视频**
🖼 <qqbot-cmd-input text="二次元图" show="二次元图"/> | 🖼 <qqbot-cmd-input text="图片功能" show="图片功能"/>
🎬 <qqbot-cmd-input text="漫剪视频" show="漫剪视频"/>

---

**🎮 游戏相关**
🎮 <qqbot-cmd-input text="王者皮肤" show="王者皮肤"/> | 🎙 <qqbot-cmd-input text="王者语音" show="王者语音"/>
🔐 <qqbot-cmd-input text="三角洲每日密码" show="三角洲每日密码"/> | 🔍 <qqbot-cmd-input text="文字找茬" show="文字找茬"/>

---

**🔧 实用工具**
🌤 <qqbot-cmd-input text="天气查询" show="天气查询"/> | 🔑 <qqbot-cmd-input text="查靓号" show="查靓号"/>
🎯 <qqbot-cmd-input text="票房排行" show="票房排行"/>

---

**🎲 娱乐休闲**
🔮 <qqbot-cmd-input text="翻塔罗牌" show="翻塔罗牌"/> | 💪 <qqbot-cmd-input text="随机超能力" show="随机超能力"/>
👧 <qqbot-cmd-input text="七濑胡桃" show="七濑胡桃"/> | 🎲 <qqbot-cmd-input text="娱乐功能" show="娱乐功能"/>

> 带有 ↗ 的文字是可以点击的';

    $键盘 = [
        "style" => ["font_size" => "small"],
        "rows" => [
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
            ]
        ]
    ];

    $this->发送('md', null, $md, $键盘);
}
