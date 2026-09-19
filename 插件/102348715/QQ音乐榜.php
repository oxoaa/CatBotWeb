<?php

use function Swoole\Coroutine\Http\get;

$apikey = '7c8c8f084709fcb51f3a0c867f1363ff9d71e0650157bcf405cb3539472372bd';

if ($this->用户信息 == 'QQ音乐榜') {
    $r = get('http://cyapi.top/API/music_hot.php?apikey=' . $apikey);
    $list = json_decode((string)$r->getBody() ?? '', true);

    if (!is_array($list) || empty($list)) {
        $this->发送('文本', '获取榜单失败');
        return;
    }

    $this->数据库('写', '点歌缓存/QQ音乐榜/' . $this->用户ID, $list);

    $msg = '请点击榜单名称查看歌曲
';
    foreach ($list as $i => $item) {
        $msg .= '![榜单 #50px #50px](' . $item['list_cover'] . ') **<qqbot-cmd-input text="音乐榜' . ($i + 1) . '" show="' . $item['list_name'] . '"/>**
';
    }

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    ['id' => '99', 'render_data' => ['label' => '猫妹交流群', 'style' => 1], 'action' => ['type' => 0, 'permission' => ['type' => 2], 'data' => 'https://qm.qq.com/q/KilBFgFV26']]
                ]
            ]
        ]
    ];

    $this->发送('md', null, $msg, $键盘);
    return;
}

if (preg_match('/^音乐榜(\d+)$/', $this->用户信息, $m)) {
    $index = (int)$m[1];
    if ($index < 1 || $index > 50) return;

    $list = $this->数据库('读', '点歌缓存/QQ音乐榜/' . $this->用户ID);
    if (!$list || !isset($list[$index - 1])) {
        $this->发送('md', null, '请先发送 <qqbot-cmd-input text="QQ音乐榜" show="QQ音乐榜"/> 获取榜单列表');
        return;
    }

    $item = $list[$index - 1];
    $listId = $item['list_id'];
    $listName = $item['list_name'];

    $r = get('http://cyapi.top/API/music_hot.php?apikey=' . $apikey . '&id=' . $listId);
    $songs = json_decode((string)$r->getBody() ?? '', true);

    if (!is_array($songs) || empty($songs)) {
        $this->发送('文本', '获取歌曲失败');
        return;
    }

    $songs = array_slice($songs, 0, 10);
    $this->数据库('写', '点歌缓存/QQ音乐榜歌/' . $this->用户ID, $songs);

    $msg = '**' . $listName . '** 请点击名称查看歌曲
';
    foreach ($songs as $i => $song) {
        $n = $i + 1;
        $msg .= '![歌曲 #50px #50px](' . $song['cover'] . ') **<qqbot-cmd-input text="音乐榜歌' . $n . '" show="' . $song['song_name'] . '"/>** - ' . $song['songer_name'] . '
';
    }

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    ['id' => '1', 'render_data' => ['label' => '返回榜单', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => 'QQ音乐榜', 'reply' => false, 'enter' => false]],
                    ['id' => '99', 'render_data' => ['label' => '猫妹交流群', 'style' => 1], 'action' => ['type' => 0, 'permission' => ['type' => 2], 'data' => 'https://qm.qq.com/q/KilBFgFV26']]
                ]
            ]
        ]
    ];

    $this->发送('md', null, $msg, $键盘);
    $this->数据库('删', '点歌缓存/QQ音乐榜/' . $this->用户ID);
    return;
}

if (preg_match('/^音乐榜歌(\d+)$/', $this->用户信息, $m)) {
    $index = (int)$m[1];
    if ($index < 1 || $index > 10) return;

    $songs = $this->数据库('读', '点歌缓存/QQ音乐榜歌/' . $this->用户ID);
    if (!$songs || !isset($songs[$index - 1])) {
        $this->发送('md', null, '请先发送 <qqbot-cmd-input text="QQ音乐榜" show="QQ音乐榜"/> 获取榜单列表');
        return;
    }

    $song = $songs[$index - 1];

    $r = get('http://cyapi.top/API/qq_music.php?apikey=' . $apikey . '&type=json&mid=' . $song['song_mid']);
    $d = json_decode((string)$r->getBody() ?? '', true);
    $url = $d['url'] ?? '';

    if (empty($url)) {
        $this->发送('文本', '播放失败');
        return;
    }

    $this->发送('md', null, '![封面 #256px #256px](' . $song['cover'] . ')
歌曲：' . $song['song_name'] . '
歌手：' . $song['songer_name'] . '
状态：点歌成功 语音发送中');
    $this->发送('语音', $url);
    $this->数据库('删', '点歌缓存/QQ音乐榜歌/' . $this->用户ID);
}