<?php

use function Swoole\Coroutine\Http\get;

if (preg_match('/^#?网易点歌\s*(.*)$/', $this->用户信息, $m)) {
    $name = trim($m[1]);

    if (!$name) {
        $this->发送('md', null, '请在后面输入要搜索的歌曲名称
例如：<qqbot-cmd-input text="网易点歌" show="网易点歌"/>红色高跟鞋');
        return;
    }

    $last = $this->数据库('读', '点歌CD/网易搜索/' . $this->用户ID) ?? 0;
    if (time() - $last < 60) {
        $s = 60 - (time() - $last);
        $this->发送('md', null, '🐱 点歌冷却中...
请等待 **' . $s . '秒**');
        return;
    }
    $this->数据库('写', '点歌CD/网易搜索/' . $this->用户ID, time());

    $url = 'https://api.xingzhige.com/API/NetEase_CloudMusic_new/?key=pY6resXL8DOqRlAYHET0nIkLC82zxt364Ni0_LbBOPE&n=&name=' . urlencode($name);
    $r = get($url);
    $d = json_decode((string)$r->getBody() ?? '', true);

    if (!is_array($d['data'] ?? null) || empty($d['data'])) {
        $this->发送('文本', '搜索失败');
        return;
    }

    $list = array_slice($d['data'], 0, 5);
    $this->数据库('写', '点歌缓存/网易/' . $this->用户ID, $list);

    $emojis = [
        '![网易点歌 #30px #30px](https://x.ocoa.cn/gif/music1.gif)',
        '![网易点歌 #30px #30px](https://x.ocoa.cn/gif/music2.gif)',
        '![网易点歌 #30px #30px](https://x.ocoa.cn/gif/music3.gif)',
        '![网易点歌 #30px #30px](https://x.ocoa.cn/gif/music4.gif)',
        '![网易点歌 #30px #30px](https://x.ocoa.cn/gif/music5.gif)'
    ];

    $msg = '![网易点歌 #30px #30px](https://x.ocoa.cn/gif/music.gif) 请点击歌名选择歌曲';
    foreach ($list as $i => $item) {
        $n = $i + 1;
        $songName = $item['songname'];
        $singer = $item['name'];
        $msg .= '
' . $emojis[$i] . ' **<qqbot-cmd-input text="网易' . $n . '" show="' . $songName . '"/>** - ' . $singer;
    }

    $this->发送('md', null, $msg);
    return;
}

if (preg_match('/^网易(\d+)$/', $this->用户信息, $m)) {
    $index = (int)$m[1];
    if ($index < 1 || $index > 5) return;

    $last = $this->数据库('读', '点歌CD/网易选择/' . $this->用户ID) ?? 0;
    if (time() - $last < 60) {
        $s = 60 - (time() - $last);
        $this->发送('md', null, '🐱 选歌冷却中...
请等待 **' . $s . '秒**');
        return;
    }
    $this->数据库('写', '点歌CD/网易选择/' . $this->用户ID, time());

    $list = $this->数据库('读', '点歌缓存/网易/' . $this->用户ID);
    if (!$list || !isset($list[$index - 1])) {
        $this->发送('md', null, '请在后面输入要搜索的歌曲名称
例如：<qqbot-cmd-input text="网易点歌" show="网易点歌"/>红色高跟鞋');
        return;
    }

    $item = $list[$index - 1];
    $url = 'https://api.xingzhige.com/API/NetEase_CloudMusic_new/?key=pY6resXL8DOqRlAYHET0nIkLC82zxt364Ni0_LbBOPE&n=1&name=' . urlencode($item['songname']);
    $r = get($url);
    $d = json_decode((string)$r->getBody() ?? '', true);

    if (empty($d['data']['src'])) {
        $this->发送('文本', '播放失败');
        return;
    }

    $cover = $d['data']['cover'] ?? '';
    $song = $d['data']['songname'] ?? $item['songname'];
    $singer = $d['data']['name'] ?? '';

    $this->发送('md', null, '![封面 #256px #256px](' . $cover . ')
歌曲：' . $song . '
歌手：' . $singer . '
状态：点歌成功 语音发送中');
    $this->发送('语音', $d['data']['src']);
    $this->数据库('删', '点歌缓存/网易/' . $this->用户ID);
}
