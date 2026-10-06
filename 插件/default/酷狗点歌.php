<?php

use function Swoole\Coroutine\Http\get;

if (preg_match('/^#?酷狗点歌\s*(.*)$/', $this->用户信息, $m)) {
    $name = trim($m[1]);

    if (!$name) {
        $this->发送('md', null, '请在后面输入要搜索的歌曲名称
例如：<qqbot-cmd-input text="酷狗点歌" show="酷狗点歌"/>红色高跟鞋');
        return;
    }

    $last = $this->数据库('读', '点歌CD/酷狗搜索/' . $this->用户ID) ?? 0;
    if (time() - $last < 60) {
        $s = 60 - (time() - $last);
        $this->发送('md', null, '🐱 点歌冷却中...
请等待 **' . $s . '秒**');
        return;
    }
    $this->数据库('写', '点歌CD/酷狗搜索/' . $this->用户ID, time());

    $url = 'https://api.yaohud.cn/api/music/kg?key=***&quality=128&n=&msg=' . urlencode($name);
    $r = get($url);
    $d = json_decode((string)$r->getBody() ?? '', true);
    $songs = $d['data']['songs'] ?? null;

    if (!is_array($songs) || empty($songs)) {
        $this->发送('文本', '搜索失败');
        return;
    }

    $list = array_slice($songs, 0, 5);
    $this->数据库('写', '点歌缓存/酷狗/' . $this->用户ID, $list);

    $emojis = [
        '![酷狗 #30px #30px](https://x.ocoa.cn/gif/music1.gif)',
        '![酷狗 #30px #30px](https://x.ocoa.cn/gif/music2.gif)',
        '![酷狗 #30px #30px](https://x.ocoa.cn/gif/music3.gif)',
        '![酷狗 #30px #30px](https://x.ocoa.cn/gif/music4.gif)',
        '![酷狗 #30px #30px](https://x.ocoa.cn/gif/music5.gif)'
    ];

    $msg = '![酷狗点歌 #30px #30px](https://x.ocoa.cn/gif/music.gif) 请点击歌名选择歌曲';
    foreach ($list as $i => $item) {
        $n = $i + 1;
        $songName = $item['name'];
        $singer = $item['singer'];
        $msg .= '
' . $emojis[$i] . ' **<qqbot-cmd-input text="酷狗' . $n . '" show="' . $songName . ' - ' . $singer . '"/>**';
    }

    $this->发送('md', null, $msg);
    return;
}

if (preg_match('/^酷狗(\d+)$/', $this->用户信息, $m)) {
    $index = (int)$m[1];
    if ($index < 1 || $index > 5) return;

    $last = $this->数据库('读', '点歌CD/酷狗选择/' . $this->用户ID) ?? 0;
    if (time() - $last < 60) {
        $s = 60 - (time() - $last);
        $this->发送('md', null, '🐱 选歌冷却中...
请等待 **' . $s . '秒**');
        return;
    }
    $this->数据库('写', '点歌CD/酷狗选择/' . $this->用户ID, time());

    $list = $this->数据库('读', '点歌缓存/酷狗/' . $this->用户ID);
    if (!$list || !isset($list[$index - 1])) {
        $this->发送('md', null, '请重新点歌
例如：<qqbot-cmd-input text="酷狗点歌" show="酷狗点歌"/>红色高跟鞋');
        return;
    }

    $item = $list[$index - 1];
    $url = 'https://api.yaohud.cn/api/music/kg?key=***&quality=128&n=' . $index . '&msg=' . urlencode($item['name']);
    $r = get($url);
    $d = json_decode((string)$r->getBody() ?? '', true);
    $data = $d['data'] ?? null;

    if (empty($data['play_url'])) {
        $this->发送('文本', '播放失败');
        return;
    }

    $this->发送('md', null, '![封面 #256px #256px](' . $data['cover'] . ')
歌曲：' . $data['name'] . '
歌手：' . $data['singer'] . '
状态：点歌成功 语音发送中');
    $this->发送('语音', $data['play_url']);
    $this->数据库('删', '点歌缓存/酷狗/' . $this->用户ID);
}
