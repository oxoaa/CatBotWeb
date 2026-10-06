<?php

use function Swoole\Coroutine\Http\get;

if (preg_match('/^#?星座运势\s*(.*)$/', $this->用户信息, $match)) {
    $constellation = trim($match[1] ?? '');

    if (empty($constellation)) {
        $md = '请点击下方星座来查询';
        $星座列表 = ['白羊座', '金牛座', '双子座', '巨蟹座', '狮子座', '处女座', '天秤座', '天蝎座', '射手座', '摩羯座', '水瓶座', '双鱼座'];
        $键盘 = [
            'style' => ['font_size' => 'small'],
            'rows' => []
        ];
        for ($i = 0; $i < 12; $i += 3) {
            $row = ['buttons' => []];
            for ($j = 0; $j < 3; $j++) {
                $星 = $星座列表[$i + $j];
                $row['buttons'][] = [
                    'id' => (string)($i + $j + 1),
                    'render_data' => ['label' => $星, 'style' => 1],
                    'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '星座运势 ' . $星, 'reply' => false, 'enter' => false]
                ];
            }
            $键盘['rows'][] = $row;
        }
        $this->发送('md', null, $md, $键盘);
        return;
    }

    // 标准化星座名称
    $map = [
        '白羊' => '白羊座', '金牛' => '金牛座', '双子' => '双子座', '巨蟹' => '巨蟹座',
        '狮子' => '狮子座', '处女' => '处女座', '天秤' => '天秤座', '天蝎' => '天蝎座',
        '射手' => '射手座', '摩羯' => '摩羯座', '水瓶' => '水瓶座', '双鱼' => '双鱼座'
    ];

    if (isset($map[$constellation])) {
        $constellation = $map[$constellation];
    } elseif (!in_array($constellation, $map)) {
        $this->发送('文本', '请输入正确的星座名称
例如：白羊座、金牛座、双子座、巨蟹座、狮子座、处女座、天秤座、天蝎座、射手座、摩羯座、水瓶座、双鱼座');
        return;
    }

    $apiKey = 'eg1ggu8KvT68ht21UMp';
    $url = 'https://api.yaohud.cn/api/v6/Horoscope?key=' . $apiKey . '&constellation=' . urlencode($constellation);
    $response = get($url);
    $result = json_decode((string)$response->getBody(), true);

    $data = $result['data']['data'];
    $index = $data['index'];
    $desc = $data['desc'];
    $title = $data['title'] ?? ($data['constellation'] . '今日运势');

    $fortune = "【{$title}】\n" .
        "📅 日期：{$data['time_range']}\n" .
        "🎯 综合：{$index['综合']}  💖 爱情：{$index['爱情']}\n" .
        "📚 事学：{$index['事学']}  💰 财富：{$index['财富']}\n" .
        "🏃 健康：{$index['健康']}  💬 商谈：{$index['商谈']}\n" .
        "✨ 幸运颜色：{$data['幸运颜色']}  🔢 幸运数字：{$data['幸运数字']}  💞 速配星座：{$data['速配星座']}\n" .
        "\n📖 详细运势：\n" .
        "```\n🔮 综合：{$desc['综合运势']}\n" .
        "💕 爱情：{$desc['爱情运势']}\n" .
        "🎓 事业/学业：{$desc['事业学业']}\n" .
        "💸 财富：{$desc['财富运势']}\n" .
        "🏋️ 健康：{$desc['健康运势']}\n```";

    $键盘 = [
        "style" => ["font_size" => "small"],
        "rows" => [
            [
                "buttons" => [
                    [
                        "id" => "1",
                        "render_data" => ["label" => "继续获取", "visited_label" => "", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "星座运势 {$constellation}", "reply" => false, "enter" => false]
                    ],
                    [
                        "id" => "2",
                        "render_data" => ["label" => "更多娱乐", "visited_label" => "", "style" => 1],
                        "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "娱乐功能", "reply" => false, "enter" => false]
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

    $this->发送('md', null, $fortune, $键盘);
}
