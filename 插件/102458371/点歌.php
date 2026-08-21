<?php

use function Swoole\Coroutine\Http\get;

const APIKEY = '7c8c8f084709fcb51f3a0c867f1363ff9d71e0650157bcf405cb3539472372bd';

// ===== 切换音源（回调按钮） =====
if (!empty($this->按钮数据) && in_array($this->按钮数据, ['src_qq', 'src_wy', 'src_kg'])) {
    $map = ['src_qq' => 'QQ点歌', 'src_wy' => '网易点歌', 'src_kg' => '酷狗点歌'];
    $label = $map[$this->按钮数据];
    $this->数据库('写', '点歌音源/' . $this->来源ID, $label);
    $kb = ['style' => ['font_size' => 'small'], 'rows' => [['buttons' => [['id' => 'input', 'render_data' => ['label' => '点歌 输入歌名', 'visited_label' => '输入歌名', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '点歌 ', 'enter' => true]]]]]];
    $this->发送('md', null, "已切换到 **{$label}**", $kb);
    return;
}

// ===== 显示切换音源面板 =====
if (!empty($this->按钮数据) && $this->按钮数据 === 'show_src') {
    $source = $this->数据库('读', '点歌音源/' . $this->来源ID) ?: '网易点歌';
    $kb = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            ['buttons' => [['id' => 'sq', 'render_data' => ['label' => 'QQ音乐', 'visited_label' => '✅ QQ音乐', 'style' => 1], 'action' => ['type' => 1, 'permission' => ['type' => 2], 'data' => 'src_qq']]]],
            ['buttons' => [['id' => 'sw', 'render_data' => ['label' => '网易云音乐', 'visited_label' => '✅ 网易云音乐', 'style' => 1], 'action' => ['type' => 1, 'permission' => ['type' => 2], 'data' => 'src_wy']]]],
            ['buttons' => [['id' => 'sk', 'render_data' => ['label' => '酷狗音乐', 'visited_label' => '✅ 酷狗音乐', 'style' => 1], 'action' => ['type' => 1, 'permission' => ['type' => 2], 'data' => 'src_kg']]]]
        ]
    ];
    $this->发送('md', null, "选择音源\n当前：**{$source}**", $kb);
    return;
}

// ===== 直接播放 =====
if (!empty($this->按钮数据) && preg_match('/^play:(\d+):([a-z0-9]+):([^:]+):([^:]+)(?::([^:]+))?$/', $this->按钮数据, $m)) {
    $index = (int)$m[1];
    $playSid = $m[2];
    $songName = urldecode($m[3]);
    $source = urldecode($m[4]);

    $song = ''; $singer = ''; $cover = ''; $src = '';

    switch ($source) {
        case 'QQ点歌':
            $r = get('http://cyapi.top/API/qq_music.php?apikey=' . APIKEY . '&type=json&n=' . ($index + 1) . '&msg=' . urlencode($songName));
            $d = json_decode((string)$r->getBody() ?? '', true);
            $song = $d['name'] ?? $songName;
            $singer = $d['artists'][0]['name'] ?? '未知';
            $cover = $d['cover']['large'] ?? $d['cover']['medium'] ?? $d['cover']['small'] ?? '';
            $src = $d['url'] ?? '';
            break;

        case '网易点歌':
            $r = get('http://cyapi.top/API/netease.php?msg=' . urlencode($songName) . '&n=' . ($index + 1) . '&type=json&apikey=' . APIKEY);
            $d = json_decode((string)$r->getBody() ?? '', true);
            $song = $d['name'] ?? $songName;
            $artists = $d['artists'] ?? [];
            $singer = is_array($artists) ? implode('/', array_column($artists, 'name')) : '未知';
            $cover = $d['cover']['large'] ?? $d['cover']['small'] ?? '';
            $src = $d['url'] ?? '';
            break;

        case '酷狗点歌':
            $kgid = isset($m[5]) ? $m[5] : '';
            if ($kgid) {
                $r = get('http://cyapi.top/API/kugou_music.php?type=play&id=' . $kgid . '&apikey=' . APIKEY);
                $d = json_decode((string)$r->getBody() ?? '', true);
                $data = $d['data'] ?? [];
                $song = $data['songName'] ?? $songName;
                $singer = $data['singerName'] ?? '未知';
                $cover = $data['albumImage'] ?? '';
                $src = $data['url'] ?? '';
            }
            break;
    }

    if (!$src) { $this->发送('md', null, '播放失败，获取不到音频'); return; }

    $msg = "![封面 #256px #256px]({$cover})\n";
    $msg .= "歌曲名称：{$song}\n";
    $msg .= "歌曲作者：{$singer}\n";
    $msg .= "当前音源：{$source}\n";
    $this->发送('md', null, $msg);
    $this->发送('语音', $src);
    return;
}

// ===== 点歌主入口 =====
if (preg_match('/^点歌\s*(.*)$/', $this->用户信息, $m)) {
    $name = trim($m[1]);
    $source = $this->数据库('读', '点歌音源/' . $this->来源ID) ?: '网易点歌';

    if (!$name) {
        $kb = [
            'style' => ['font_size' => 'small'],
            'rows' => [
                ['buttons' => [['id' => 'input', 'render_data' => ['label' => '输入歌名搜索', 'visited_label' => '输入歌名', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '点歌 ', 'enter' => true]]]],
                ['buttons' => [['id' => 'sq', 'render_data' => ['label' => 'QQ音乐', 'visited_label' => '✅ QQ音乐', 'style' => 1], 'action' => ['type' => 1, 'permission' => ['type' => 2], 'data' => 'src_qq']]]],
                ['buttons' => [['id' => 'sw', 'render_data' => ['label' => '网易云音乐', 'visited_label' => '✅ 网易云音乐', 'style' => 1], 'action' => ['type' => 1, 'permission' => ['type' => 2], 'data' => 'src_wy']]]],
                ['buttons' => [['id' => 'sk', 'render_data' => ['label' => '酷狗音乐', 'visited_label' => '✅ 酷狗音乐', 'style' => 1], 'action' => ['type' => 1, 'permission' => ['type' => 2], 'data' => 'src_kg']]]]
            ]
        ];
        $this->发送('md', null, "点歌\n当前音源：**{$source}**", $kb);
        return;
    }

    $list = [];

    switch ($source) {
        case 'QQ点歌':
            $r = get('http://cyapi.top/API/qq_music.php?apikey=' . APIKEY . '&num=10&type=json&msg=' . urlencode($name));
            $d = json_decode((string)$r->getBody() ?? '', true);
            $raw = $d['list'] ?? [];
            foreach ($raw as $item) {
                $item['_sname'] = $item['name'];
                $item['_singer'] = $item['artists'] ?? '';
                $item['_label'] = $item['name'] . ' - ' . ($item['artists'] ?? '');
                $list[] = $item;
                if (count($list) >= 10) break;
            }
            break;

        case '网易点歌':
            $r = get('http://cyapi.top/API/netease.php?msg=' . urlencode($name) . '&num=20&type=json&apikey=' . APIKEY);
            $d = json_decode((string)$r->getBody() ?? '', true);
            $raw = $d['list'] ?? [];
            foreach ($raw as $item) {
                $item['_sname'] = $item['name'];
                $item['_singer'] = $item['artists'] ?? '';
                $item['_label'] = $item['name'] . ' - ' . ($item['artists'] ?? '');
                $list[] = $item;
                if (count($list) >= 10) break;
            }
            break;

        case '酷狗点歌':
            $r = get('http://cyapi.top/API/kugou_music.php?msg=' . urlencode($name) . '&apikey=' . APIKEY);
            $d = json_decode((string)$r->getBody() ?? '', true);
            $raw = $d['list'] ?? [];
            foreach ($raw as $item) {
                $item['_sname'] = $item['SongName'];
                $item['_singer'] = $item['SingerName'] ?? '';
                $item['_label'] = $item['SongName'] . ' - ' . ($item['SingerName'] ?? '');
                $list[] = $item;
                if (count($list) >= 10) break;
            }
            break;
    }

    if (empty($list)) {
        $this->发送('md', null, "没有找到相关歌曲\n换首歌试试？");
        return;
    }

    // Markdown 列表
    $msg = "**{$source} | {$name}**\n---";
    foreach ($list as $i => $item) {
        $n = $i + 1;
        $msg .= "\n{$n}. {$item['_label']}";
    }

    // 按钮：切换音源 + 每行5个数字按钮
    $row1 = []; $row2 = [];
    foreach ($list as $i => $item) {
        $btnSid = substr(md5(uniqid((string)mt_rand(), true)), 0, 6);
        $encodedName = urlencode($item['_sname']);
        $extra = '';
        if ($source === '酷狗点歌') {
            $extra = ':' . $item['id'];
        }
        $btn = [
            'id' => "p{$i}",
            'render_data' => ['label' => (string)($i + 1), 'visited_label' => '✅ ' . (string)($i + 1), 'style' => 1],
            'action' => ['type' => 1, 'permission' => ['type' => 2], 'data' => "play:{$i}:{$btnSid}:{$encodedName}:{$source}{$extra}"]
        ];
        if ($i < 5) $row1[] = $btn;
        else $row2[] = $btn;
    }

    $kb = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            ['buttons' => [['id' => 'switch', 'render_data' => ['label' => '切换音源', 'visited_label' => '切换音源', 'style' => 1], 'action' => ['type' => 1, 'permission' => ['type' => 2], 'data' => 'show_src']]]],
            ['buttons' => $row1],
            ['buttons' => $row2]
        ]
    ];

    $this->发送('md', null, $msg, $kb);
    return;
}
