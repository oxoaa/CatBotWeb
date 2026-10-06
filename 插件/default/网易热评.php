<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '网易热评') {
    $r = get('http://oiapi.net/api/NeteaseHotReviews');
    $d = json_decode((string)$r->getBody() ?? '', true);

    if (empty($d['data'])) {
        $this->发送('文本', '获取失败');
        return;
    }

    $data = $d['data'];
    $author = $data['author'] ?? [];

    $md = '![封面 #256px #256px](' . ($data['cover'] ?? '') . ')
歌曲：' . ($data['song'] ?? '') . '
歌手：' . ($data['singer'] ?? '') . '
日期：' . ($data['timestr'] ?? '') . '
点赞：' . ($data['like'] ?? '') . '
评论者：' . ($author['nick'] ?? '') . '
评论：' . ($data['content'] ?? '');

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    ['id' => '1', 'render_data' => ['label' => '继续获取', 'visited_label' => '', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '网易热评', 'reply' => false, 'enter' => false]],
                    ['id' => '2', 'render_data' => ['label' => '更多娱乐', 'visited_label' => '', 'style' => 1], 'action' => ['type' => 2, 'permission' => ['type' => 2], 'data' => '娱乐功能', 'reply' => false, 'enter' => false]]
                ]
            ],
            [
                'buttons' => [
                    ['id' => '99', 'render_data' => ['label' => '猫妹交流群', 'style' => 1], 'action' => ['type' => 0, 'permission' => ['type' => 2], 'data' => 'https://qm.qq.com/q/KilBFgFV26']]
                ]
            ]
        ]
    ];

    $this->发送('md', null, $md, $键盘);
}
