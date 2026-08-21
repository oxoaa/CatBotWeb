<?php
/**
 * 群聊管理插件 - 进退群提示 / 撤回 / 关键词过滤
 */

// ===== 存储消息 ID =====
if (in_array($this->事件类型 ?? '', ['GROUP_MESSAGE_CREATE', 'GROUP_AT_MESSAGE_CREATE'])) {
    if (!empty($this->来源ID) && !empty($this->用户ID) && !empty($this->信息ID)) {
        $msgs = $this->数据库('读', "msgids/{$this->来源ID}/{$this->用户ID}") ?: [];
        $msgs[] = ['id' => $this->信息ID, 'ts' => time()];
        if (count($msgs) > 50) array_shift($msgs);
        $this->数据库('写', "msgids/{$this->来源ID}/{$this->用户ID}", $msgs);
    }
}

// ===== 关键词自动撤回 =====
if (in_array($this->事件类型 ?? '', ['GROUP_MESSAGE_CREATE', 'GROUP_AT_MESSAGE_CREATE'])) {
    $content = trim($this->用户信息 ?? '');
    if ($content !== '') {
        $list = $this->数据库('读', "keywordrecall/{$this->来源ID}") ?? [];
        if (is_array($list) && !empty($list)) {
            foreach ($list as $kw) {
                if (mb_strpos($content, $kw) !== false) {
                    if (!empty($this->信息ID)) $this->撤回($this->信息ID);
                    break;
                }
            }
        }
    }
}

// ===== 媒体自动撤回 =====
if (in_array($this->事件类型 ?? '', ['GROUP_MESSAGE_CREATE', 'GROUP_AT_MESSAGE_CREATE'])) {
    $content = trim($this->用户信息 ?? '');
    if ($content === '') {
        $raw = $this->消息原始数据 ?? [];
        $mediaType = '';
        if (!empty($raw['media']['media_type'])) {
            $map = [1 => 'image', 2 => 'video', 3 => 'voice', 4 => 'file'];
            $mediaType = $map[$raw['media']['media_type']] ?? '';
        } elseif (!empty($raw['attachments'][0]['content_type'])) {
            $ct = $raw['attachments'][0]['content_type'];
            if (strpos($ct, 'image/') === 0) $mediaType = 'image';
            elseif (strpos($ct, 'video/') === 0) $mediaType = 'video';
            elseif (strpos($ct, 'audio/') === 0) $mediaType = 'voice';
            else $mediaType = 'file';
        }
        if ($mediaType) {
            $set = $this->数据库('读', "recallsettings/{$this->来源ID}") ?: [];
            if (!empty($set[$mediaType]) && !empty($this->信息ID)) $this->撤回($this->信息ID);
        }
    }
}

// ===== 链接自动撤回 =====
if (in_array($this->事件类型 ?? '', ['GROUP_MESSAGE_CREATE', 'GROUP_AT_MESSAGE_CREATE'])) {
    $set = $this->数据库('读', "recallsettings/{$this->来源ID}") ?: [];
    if (!empty($set['link'])) {
        $content = trim($this->用户信息 ?? '');
        $raw = $this->消息原始数据 ?? [];
        $hasLink = false;
        if (strpos($content, 'http://') !== false || strpos($content, 'https://') !== false) $hasLink = true;
        if (!$hasLink && preg_match('/[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}/', $content)) $hasLink = true;
        if (!empty($raw['ark_data'])) $hasLink = true;
        if ($hasLink && !empty($this->信息ID)) $this->撤回($this->信息ID);
    }
}

// ===== 名片自动撤回 =====
if (in_array($this->事件类型 ?? '', ['GROUP_MESSAGE_CREATE', 'GROUP_AT_MESSAGE_CREATE'])) {
    $set = $this->数据库('读', "recallsettings/{$this->来源ID}") ?: [];
    if (!empty($set['card'])) {
        $raw = $this->消息原始数据 ?? [];
        if (!empty($raw['message_type']) && $raw['message_type'] == 3 && !empty($raw['ark_data'])) {
            if (!empty($this->信息ID)) $this->撤回($this->信息ID);
        }
    }
}

// ===== 辅助函数 =====
if (!function_exists('_btn')) {
function _btn($id, $label, $style, $data) {
    return ['id'=>$id,'render_data'=>['label'=>$label,'style'=>$style],
        'action'=>['type'=>1,'permission'=>['type'=>1],'data'=>$data]];
}
}
if (!function_exists('_管理面板')) {
function _管理面板() {
    return ['style'=>['font_size'=>'small'],'rows'=>[['buttons'=>[
        _btn('btn_jcq','进退群管理',4,'进退群管理'),
        _btn('btn_recall','撤回管理',4,'撤回管理'),
    ]]]];
}
}
if (!function_exists('_进退群面板')) {
function _进退群面板($set) {
    $j=($set['join']??false)?'[开]':'[关]';$t=($set['leave']??false)?'[开]':'[关]';
    return ['style'=>['font_size'=>'small'],'rows'=>[
        ['buttons'=>[_btn('btn_join_on',"{$j} 开启进群提示",1,'开启进群提示'),_btn('btn_join_off',"{$j} 关闭进群提示",1,'关闭进群提示')]],
        ['buttons'=>[_btn('btn_leave_on',"{$t} 开启退群提示",1,'开启退群提示'),_btn('btn_leave_off',"{$t} 关闭退群提示",1,'关闭退群提示')]],
        ['buttons'=>[_btn('btn_back','返回',3,'返回管理')]]
    ]];
}
}
if (!function_exists('_撤回面板')) {
function _撤回面板($kwList,$recallSet=[]) {
    $rows=[];$img=!empty($recallSet['image'])?'[开]':'[关]';$vid=!empty($recallSet['video'])?'[开]':'[关]';
    $voc=!empty($recallSet['voice'])?'[开]':'[关]';$fil=!empty($recallSet['file'])?'[开]':'[关]';
    $lk=!empty($recallSet['link'])?'[开]':'[关]';$cd=!empty($recallSet['card'])?'[开]':'[关]';
    $rows[] = ['buttons'=>[
        _btn('btn_img',"{$img} 图片",1,'切换图片撤回'),
        _btn('btn_vid',"{$vid} 视频",1,'切换视频撤回'),
        _btn('btn_voc',"{$voc} 语音",1,'切换语音撤回'),
    ]];
    $rows[] = ['buttons'=>[
        _btn('btn_fil',"{$fil} 文件",1,'切换文件撤回'),
        _btn('btn_lk',"{$lk} 链接",1,'切换链接撤回'),
        _btn('btn_cd',"{$cd} 名片",1,'切换名片撤回'),
    ]];
    $kwText = empty($kwList) ? '暂无' : count($kwList).'个';
    $rows[] = ['buttons'=>[_btn('btn_kws',"关键词: {$kwText}",2,'查看撤回')]];
    $rows[] = ['buttons'=>[_btn('btn_back2','返回',3,'返回管理')]];
    return ['style'=>['font_size'=>'small'],'rows'=>$rows];
}
}

// ===== 交互按钮回调 =====
if ($this->事件类型 === 'ShengBot_MSG') {
    $action = trim($this->按钮数据 ?? '');
    if (!$this->是管理员()) { $this->发送('md', null, '**权限不足**'); return; }

    switch ($action) {
        case '进退群管理':
            $set = $this->数据库('读', "eventsettings/{$this->来源ID}") ?: [];
            $this->发送('md', null, "**进退群管理**", _进退群面板($set));
            return;
        case '撤回管理':
            $kwList = $this->数据库('读', "keywordrecall/{$this->来源ID}") ?? [];
            $recallSet = $this->数据库('读', "recallsettings/{$this->来源ID}") ?: [];
            $this->发送('md', null, "**撤回管理**", _撤回面板($kwList, $recallSet));
            return;
        case '返回管理':
            $this->发送('md', null, "**群聊管理中心**", _管理面板());
            return;
        case '开启进群提示':
        case '关闭进群提示':
            $val = ($action === '开启进群提示');
            $cur = $this->数据库('读', "eventsettings/{$this->来源ID}") ?: [];
            $cur['join'] = $val;
            $this->数据库('写', "eventsettings/{$this->来源ID}", $cur);
            $this->发送('md', null, $val ? '**已开启** 进群提示' : '**已关闭** 进群提示', _进退群面板($cur));
            return;
        case '开启退群提示':
        case '关闭退群提示':
            $val = ($action === '开启退群提示');
            $cur = $this->数据库('读', "eventsettings/{$this->来源ID}") ?: [];
            $cur['leave'] = $val;
            $this->数据库('写', "eventsettings/{$this->来源ID}", $cur);
            $this->发送('md', null, $val ? '**已开启** 退群提示' : '**已关闭** 退群提示', _进退群面板($cur));
            return;
    }

    $typeMap = [
        '切换图片撤回' => 'image', '切换视频撤回' => 'video',
        '切换语音撤回' => 'voice', '切换文件撤回' => 'file',
        '切换链接撤回' => 'link',  '切换名片撤回' => 'card',
    ];
    if (isset($typeMap[$action])) {
        $key = $typeMap[$action];
        $cur = $this->数据库('读', "recallsettings/{$this->来源ID}") ?: [];
        $cur[$key] = empty($cur[$key]);
        $this->数据库('写', "recallsettings/{$this->来源ID}", $cur);
        $kwList = $this->数据库('读', "keywordrecall/{$this->来源ID}") ?? [];
        $label = ['image'=>'图片','video'=>'视频','voice'=>'语音','file'=>'文件','link'=>'链接','card'=>'名片'][$key];
        $this->发送('md', null, ($cur[$key]?'**已开启**':'**已关闭**')." {$label}撤回", _撤回面板($kwList, $cur));
        return;
    }
}

// ===== 指令：撤回某人消息 =====
if (in_array($this->事件类型 ?? '', ['GROUP_MESSAGE_CREATE', 'GROUP_AT_MESSAGE_CREATE'])) {
    $c = trim($this->用户信息 ?? '');
    $isRecallCmd = false; $targetId = null; $recallCount = 1;

    if (preg_match('/撤回/i', $c)) {
        if ($this->事件类型 === 'GROUP_MESSAGE_CREATE' && !empty($this->艾特用户)) {
            $targetId = strtoupper($this->艾特用户);
            preg_match('/(\d+)$/', $c, $cm);
            $recallCount = max(1, min(intval($cm[1] ?? 1), 10));
            $isRecallCmd = true;
        } elseif ($this->事件类型 === 'GROUP_AT_MESSAGE_CREATE') {
            preg_match_all('/<@([A-F0-9]+)>/i', $c, $atMentions);
            if (!empty($atMentions[1])) {
                $targetId = strtoupper(end($atMentions[1]));
                preg_match('/(\d+)$/', $c, $cm);
                $recallCount = max(1, min(intval($cm[1] ?? 1), 10));
                $isRecallCmd = true;
            }
        }
    }

    if ($isRecallCmd && $targetId) {
        $msgs = $this->数据库('读', "msgids/{$this->来源ID}/{$targetId}") ?: [];
        if (empty($msgs)) { $this->发送('md', null, '**无消息可撤回**'); return; }
        $toRecall = array_slice($msgs, -$recallCount);
        $suc = 0; $fail = 0;
        foreach ($toRecall as $m) { if ($this->撤回($m['id'])) $suc++; else $fail++; usleep(100000); }
        $msgs = array_slice($msgs, 0, -$recallCount);
        if (!empty($msgs)) $this->数据库('写', "msgids/{$this->来源ID}/{$targetId}", $msgs);
        else $this->数据库('删', "msgids/{$this->来源ID}/{$targetId}");
        $this->发送('md', null, "**已撤回 {$suc} 条" . ($fail > 0 ? "，{$fail} 条失败" : '') . "**");
        return;
    }
}

// ===== 进退群事件 =====
if ($this->事件类型 === 'GROUP_MEMBER_ADD') {
    $set = $this->数据库('读', "eventsettings/{$this->来源ID}");
    if ($set && !empty($set['join'])) {
        $this->发送('md', null, "**欢迎** <qqbot-at-user id=\"{$this->用户ID}\" />\n" . date('Y-m-d H:i:s'));
    }
    return;
}
if ($this->事件类型 === 'GROUP_MEMBER_REMOVE') {
    $set = $this->数据库('读', "eventsettings/{$this->来源ID}");
    if ($set && !empty($set['leave'])) {
        $this->发送('md', null, "**有成员退群**\n" . date('Y-m-d H:i:s'));
    }
    return;
}

// ===== 文本指令 =====
if (!in_array($this->事件类型 ?? '', ['GROUP_AT_MESSAGE_CREATE', 'GROUP_MESSAGE_CREATE'])) return;
$msg = trim($this->用户信息 ?? '');
if (empty($msg)) return;

if ($msg === '管理' || $msg === '群聊管理') {
    $this->发送('md', null, "**群聊管理中心**", _管理面板());
    return;
}

if (preg_match('/^加撤回\s+(.+)$/u', $msg, $m)) {
    if (!$this->是管理员()) { $this->发送('md', null, '**权限不足**'); return; }
    $kw = trim($m[1]); if (empty($kw)) return;
    $list = $this->数据库('读', "keywordrecall/{$this->来源ID}") ?? [];
    if (!is_array($list)) $list = [];
    if (in_array($kw, $list)) { $this->发送('md', null, "关键词 `{$kw}` 已存在"); return; }
    $list[] = $kw;
    $this->数据库('写', "keywordrecall/{$this->来源ID}", $list);
    $this->发送('md', null, "**已添加** `{$kw}`\n当前共 " . count($list) . " 个");
    return;
}

if (preg_match('/^删撤回\s+(.+)$/u', $msg, $m)) {
    if (!$this->是管理员()) { $this->发送('md', null, '**权限不足**'); return; }
    $kw = trim($m[1]); if (empty($kw)) return;
    $list = $this->数据库('读', "keywordrecall/{$this->来源ID}") ?? [];
    if (!is_array($list)) $list = [];
    $idx = array_search($kw, $list);
    if ($idx === false) { $this->发送('md', null, "关键词 `{$kw}` 不存在"); return; }
    array_splice($list, $idx, 1);
    $this->数据库('写', "keywordrecall/{$this->来源ID}", $list);
    $this->发送('md', null, "**已删除** `{$kw}`");
    return;
}

if ($msg === '查看撤回') {
    $list = $this->数据库('读', "keywordrecall/{$this->来源ID}") ?? [];
    if (empty($list) || !is_array($list)) { $this->发送('md', null, '**暂无撤回关键词**'); return; }
    $txt = "**撤回关键词** (共 " . count($list) . " 个)\n---\n";
    foreach ($list as $i => $kw) $txt .= ($i + 1) . ". `{$kw}`\n";
    $this->发送('md', null, $txt);
    return;
}

switch ($msg) {
    case '开启进群提示':
        $cur = $this->数据库('读', "eventsettings/{$this->来源ID}") ?: [];
        $cur['join'] = true;
        $this->数据库('写', "eventsettings/{$this->来源ID}", $cur);
        $this->发送('md', null, '**已开启** 进群提示');
        break;
    case '关闭进群提示':
        $cur = $this->数据库('读', "eventsettings/{$this->来源ID}") ?: [];
        $cur['join'] = false;
        $this->数据库('写', "eventsettings/{$this->来源ID}", $cur);
        $this->发送('md', null, '**已关闭** 进群提示');
        break;
    case '开启退群提示':
        $cur = $this->数据库('读', "eventsettings/{$this->来源ID}") ?: [];
        $cur['leave'] = true;
        $this->数据库('写', "eventsettings/{$this->来源ID}", $cur);
        $this->发送('md', null, '**已开启** 退群提示');
        break;
    case '关闭退群提示':
        $cur = $this->数据库('读', "eventsettings/{$this->来源ID}") ?: [];
        $cur['leave'] = false;
        $this->数据库('写', "eventsettings/{$this->来源ID}", $cur);
        $this->发送('md', null, '**已关闭** 退群提示');
        break;
    case '进退群状态':
        $set = $this->数据库('读', "eventsettings/{$this->来源ID}") ?: [];
        $j = ($set['join'] ?? false) ? '开启' : '关闭';
        $l = ($set['leave'] ?? false) ? '开启' : '关闭';
        $this->发送('md', null, "**进退群状态**\n---\n进群: {$j}\n退群: {$l}");
        break;
}
