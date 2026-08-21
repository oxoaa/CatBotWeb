<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '王者语音') {
    $heroList = [
        '安琪拉','白起','老夫子','不知火舞','妲己','狄仁杰','典韦','韩信','刘邦','刘禅',
        '鲁班七号','墨子','孙膑','孙尚香','孙悟空','项羽','亚瑟','周瑜','庄周',
        '蔡文姬','武则天','廉颇','程咬金','后羿','扁鹊','钟无艳','花木兰','小乔','王昭君',
        '虞姬','甄姬','李元芳','张飞','刘备','牛魔','张良',
        '兰陵王','露娜','东皇太一','貂蝉','达摩','曹操','芈月','阿轲','高渐离','钟馗',
        '关羽','李白','宫本武藏','吕布','嬴政','娜可露露','赵云','姜子牙','橘右京',
        '干将莫邪','鬼谷子','诸葛亮','哪吒','太乙真人','黄忠','大乔','铠','百里守约',
        '百里玄策','苏烈','梦奇','女娲','明世隐','公孙离','杨玉环','裴擒虎','弈星',
        '狂铁','米莱狄','元歌','孙策','司马懿','盾山','伽罗','沈梦溪','李信','上官婉儿',
        '嫦娥','猪八戒','瑶','云中君','曜','马超','西施','鲁班大师','蒙犽','镜',
        '蒙恬','阿古朵','夏洛特','澜','司空震','艾琳','云缨','金蝉','暃','桑启',
        '戈娅','海月','赵怀真','莱西奥','姬小满','亚连','朵莉亚','海诺'
    ];

    $hero = $heroList[array_rand($heroList)];

    $response = get('http://api.yujn.cn/api/wzyyb.php?msg=' . urlencode($hero));
    $json = json_decode((string)$response->getBody() ?? '', true);

    if (!empty($json['data'])) {
        $item = $json['data'][array_rand($json['data'])];
        $heroName = $json['hero'];
        $lines = $item['lines'];

        $md = '### 🎮 英雄名称：' . $heroName . '
### 🗣️ 英雄台词：' . $lines . '
### 🔊 语音发送中～';

        $键盘 = [
            "style" => ["font_size" => "small"],
            "rows" => [
                [
                    "buttons" => [
                        ["id" => "1", "render_data" => ["label" => "再来一首", "style" => 1], "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "王者语音", "reply" => false, "enter" => false]],
                        ["id" => "2", "render_data" => ["label" => "更多音乐", "style" => 1], "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "音乐功能", "reply" => false, "enter" => false]]
                    ]
                ]
            ]
        ];

        $this->发送('md', null, $md, $键盘);
        $this->发送('语音', $item['voice']);
    }
}
