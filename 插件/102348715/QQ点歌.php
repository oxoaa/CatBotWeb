<?php

use function Swoole\Coroutine\Http\get;

if (preg_match('/^#?QQ点歌\s*(.*)$/', $this->用户信息, $m)) {
    $name = trim($m[1]);

    if (!$name) {
        $this->发送('md', null, '请在后面输入要搜索的歌曲名称
例如：<qqbot-cmd-input text="QQ点歌" show="QQ点歌"/>红色高跟鞋');
        return;
    }

    $last = $this->数据库('读', '点歌CD/QQ搜索/' . $this->用户ID) ?? 0;
    if (time() - $last < 60) {
        $s = 60 - (time() - $last);
        $this->发送('md', null, '🐱 点歌冷却中...
请等待 **' . $s . '秒**');
        return;
    }
    $this->数据库('写', '点歌CD/QQ搜索/' . $this->用户ID, time());

    $url = 'http://cyapi.top/API/qq_music.php?apikey=7c8c8f084709fcb51f3a0c867f1363ff9d71e0650157bcf405cb3539472372bd&num=10&type=json&msg=' . urlencode($name);
    $r = get($url);
    $d = json_decode((string)$r->getBody() ?? '', true);

    if (!is_array($d['list'] ?? null) || empty($d['list'])) {
        $this->发送('文本', '搜索失败');
        return;
    }

    $list = array_slice($d['list'], 0, 5);
    $this->数据库('写', '点歌缓存/QQ/' . $this->用户ID, $list);

    $emojis = [
        '![QQ点歌 #30px #30px](http://api.ocoa.cn/api/catemoji/90.gif)',
        '![QQ点歌 #30px #30px](http://api.ocoa.cn/api/catemoji/91.gif)',
        '![QQ点歌 #30px #30px](http://api.ocoa.cn/api/catemoji/92.gif)',
        '![QQ点歌 #30px #30px](http://api.ocoa.cn/api/catemoji/93.gif)',
        '![QQ点歌 #30px #30px](http://api.ocoa.cn/api/catemoji/94.gif)'
    ];

    $msg = '![QQ点歌 #30px #30px](http://api.ocoa.cn/api/catemoji/60.gif) 请点击歌名选择歌曲';
    foreach ($list as $i => $item) {
        $artists = is_array($item['artists'] ?? null) ? implode('/', array_column($item['artists'], 'name')) : ($item['artists'] ?? '');
        $n = $i + 1;
        $songName = $item['name'];
        $msg .= '
' . $emojis[$i] . ' **<qqbot-cmd-input text="QQ' . $n . '" show="' . $songName . '"/>** - ' . $artists;
    }

    $this->发送('md', null, $msg);
    return;
}

if (preg_match('/^QQ(\d+)$/', $this->用户信息, $m)) {
    $index = (int)$m[1];
    if ($index < 1 || $index > 5) return;

    $last = $this->数据库('读', '点歌CD/QQ选择/' . $this->用户ID) ?? 0;
    if (time() - $last < 60) {
        $s = 60 - (time() - $last);
        $this->发送('md', null, '🐱 选歌冷却中...
请等待 **' . $s . '秒**');
        return;
    }
    $this->数据库('写', '点歌CD/QQ选择/' . $this->用户ID, time());

    $list = $this->数据库('读', '点歌缓存/QQ/' . $this->用户ID);
    if (!$list || !isset($list[$index - 1])) {
        $this->发送('md', null, '请在后面输入要搜索的歌曲名称
例如：<qqbot-cmd-input text="QQ点歌" show="QQ点歌"/>红色高跟鞋');
        return;
    }

    $item = $list[$index - 1];
    $url = 'http://cyapi.top/API/qq_music.php?apikey=7c8c8f084709fcb51f3a0c867f1363ff9d71e0650157bcf405cb3539472372bd&type=json&n=' . $index . '&msg=' . urlencode($item['name']);
    $r = get($url);
    $d = json_decode((string)$r->getBody() ?? '', true);

    if (empty($d['url'])) {
        $this->发送('文本', '播放失败');
        return;
    }

    $song = $d['name'] ?? $item['name'];
    $singer = ($d['artists'][0]['name'] ?? '未知');
    $cover = $d['cover']['large'] ?? ($item['cover'] ?? '');

    $this->发送('md', null, '![封面 #256px #256px](' . $cover . ')
歌曲：' . $song . '
歌手：' . $singer . '
状态：点歌成功 语音发送中');
    $this->发送('语音', $d['url']);
    $this->数据库('删', '点歌缓存/QQ/' . $this->用户ID);
}
