<?php

use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == '三角洲每日密码') {
    $r = get('https://jcy.meiaodai.xyz/api/api/mm.php');
    $d = json_decode((string)$r->getBody() ?? '', true);

    if (empty($d['data'])) {
        $this->发送('文本', '获取失败');
        return;
    }

    $md = '';
    $total = count($d['data']);

    foreach ($d['data'] as $i => $item) {
        $size = ($i == 1) ? '604px #859px' : '1373px #934px';
        $md .= $item['name'] . '
> 密码：' . $item['password'] . '
> 位置：' . $item['location'] . '

![密码 #' . $size . '](' . $item['image'] . ')';
        if ($i < $total - 1) {
            $md .= '

---

';
        }
    }

    $键盘 = [
        'style' => ['font_size' => 'small'],
        'rows' => [
            [
                'buttons' => [
                    ['id' => '1', 'render_data' => ['label' => '猫妹交流群', 'visited_label' => '', 'style' => 1], 'action' => ['type' => 0, 'permission' => ['type' => 2], 'data' => 'https://qm.qq.com/q/KilBFgFV26', 'reply' => false, 'enter' => false]]
                ]
            ]
        ]
    ];

    $this->发送('md', null, $md, $键盘);
}
