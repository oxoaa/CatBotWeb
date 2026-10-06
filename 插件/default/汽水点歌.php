<?php

use function Swoole\Coroutine\Http\get;

if (preg_match('/^#?汽水点歌\s*(.*)$/', $this->用户信息, $m)) {
    $name = trim($m[1]);

    if (!$name) {
        $this->发送('md', null, '请在后面输入要搜索的歌曲名称
例如：<qqbot-cmd-input text="汽水点歌" show="汽水点歌"/>红色高跟鞋');
        return;
    }

    $last = $this->数据库('读', '点歌CD/汽水搜索/' . $this->用户ID) ?? 0;
    if (time() - $last < 60) {
        $s = 60 - (time() - $last);
        $this->发送('md', null, '🐱 点歌冷却中...
请等待 **' . $s . '秒**');
        return;
    }
    $this->数据库('写', '点歌CD/汽水搜索/' . $this->用户ID, time());

    $url = 'https://api.s01s.cn/API/qsyy/?b=&msg=' . urlencode($name);
    $r = get($url);
    $text = (string)$r->getBody() ?? '';

    if (!str_contains($text, ':')) {
        $this->发送('文本', '搜索失败');
        return;
    }

    $rawList = preg_split('/(?=\d+:)/', $text);
    $list = [];
    foreach (array_slice($rawList, 0, 5) as $item) {
        if (preg_match('/^\d+:(.+?)\((.+?)\)/', $item, $mm)) {
            $list[] = ['song' => trim($mm[1]), 'author' => trim($mm[2])];
        }
    }

    if (empty($list)) {
        $this->发送('文本', '搜索失败');
        return;
    }

    $this->数据库('写', '点歌缓存/汽水/' . $this->用户ID, $list);

    $emojis = [
        '![汽水点歌 #30px #30px](https://x.ocoa.cn/gif/music1.gif)',
        '![汽水点歌 #30px #30px](https://x.ocoa.cn/gif/music2.gif)',
        '![汽水点歌 #30px #30px](https://x.ocoa.cn/gif/music3.gif)',
        '![汽水点歌 #30px #30px](https://x.ocoa.cn/gif/music4.gif)',
        '![汽水点歌 #30px #30px](https://x.ocoa.cn/gif/music5.gif)'
    ];

    $msg = '![汽水点歌 #30px #30px](https://x.ocoa.cn/gif/music.gif) 请点击歌名选择歌曲';
    foreach ($list as $i => $item) {
        $n = $i + 1;
        $songName = $item['song'];
        $author = $item['author'];
        $msg .= '
' . $emojis[$i] . ' **<qqbot-cmd-input text="汽水' . $n . '" show="' . $songName . '"/>** - ' . $author;
    }

    $this->发送('md', null, $msg);
    return;
}

if (preg_match('/^汽水(\d+)$/', $this->用户信息, $m)) {
    $index = (int)$m[1];
    if ($index < 1 || $index > 5) return;

    $last = $this->数据库('读', '点歌CD/汽水选择/' . $this->用户ID) ?? 0;
    if (time() - $last < 60) {
        $s = 60 - (time() - $last);
        $this->发送('md', null, '🐱 选歌冷却中...
请等待 **' . $s . '秒**');
        return;
    }
    $this->数据库('写', '点歌CD/汽水选择/' . $this->用户ID, time());

    $list = $this->数据库('读', '点歌缓存/汽水/' . $this->用户ID);
    if (!$list || !isset($list[$index - 1])) {
        $this->发送('md', null, '请先搜索歌曲
例如：<qqbot-cmd-input text="汽水点歌" show="汽水点歌"/>红色高跟鞋');
        return;
    }

    $item = $list[$index - 1];
    $url = 'https://api.s01s.cn/API/qsyy/?b=1&msg=' . urlencode($item['song']);
    $r = get($url);
    $d = json_decode((string)$r->getBody() ?? '', true);

    if (empty($d['url'])) {
        $this->发送('文本', '播放失败');
        return;
    }

    $this->发送('md', null, '![封面 #256px #256px](' . $d['cover'] . ')
歌曲：' . $d['name'] . '
歌手：' . $d['author'] . '
状态：点歌成功 语音发送中');
    $this->发送('语音', $d['url']);
    $this->数据库('删', '点歌缓存/汽水/' . $this->用户ID);
}
