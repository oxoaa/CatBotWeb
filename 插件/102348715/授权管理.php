<?php
/**
 * 插件授权管理
 * 指令：
 *   加授权 <appid> <天数>  — 为机器人授权指定天数
 *   删授权 <appid>         — 移除机器人授权
 *   查授权                 — 查看所有授权
 */

$授权文件 = __DIR__ . '/../../数据/授权.json';
if (!is_dir(dirname($授权文件))) mkdir(dirname($授权文件), 0755, true);

// 读取授权数据
$授权 = file_exists($授权文件) ? (json_decode(file_get_contents($授权文件), true) ?? []) : [];
$消息 = trim($this->用户信息);

// === 加授权 ===
if (preg_match('/^加授权\s+(\S+)\s+(\d+)$/u', $消息, $m)) {
    $目标ID = $m[1];
    $天数 = (int)$m[2];
    if ($天数 <= 0) {
        $this->发送('文本', "❌ 天数必须大于0");
        return;
    }
    $过期时间 = time() + $天数 * 86400;
    $授权[$目标ID] = ['天数' => $天数, '过期' => $过期时间, '添加时间' => date('Y-m-d H:i:s')];
    file_put_contents($授权文件, json_encode($授权, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $this->发送('文本', "✅ 已授权 $目标ID {$天数}天\n过期时间：" . date('Y-m-d H:i:s', $过期时间));
    return;
}

// === 删授权 ===
if (preg_match('/^删授权\s+(\S+)$/u', $消息, $m)) {
    $目标ID = $m[1];
    if (isset($授权[$目标ID])) {
        unset($授权[$目标ID]);
        file_put_contents($授权文件, json_encode($授权, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->发送('文本', "✅ 已移除 $目标ID 的授权");
    } else {
        $this->发送('文本', "❌ $目标ID 未授权，无法删除");
    }
    return;
}

// === 查授权 ===
if ($消息 === '查授权') {
    $当前时间 = time();
    if (empty($授权)) {
        $this->发送('文本', "📋 当前无任何授权");
        return;
    }
    $列表 = "📋 授权列表：\n";
    foreach ($授权 as $id => $info) {
        $过期 = $info['过期'] ?? 0;
        $剩余 = $过期 - $当前时间;
        $状态 = $剩余 > 0 ? "✅ 有效（剩余" . ceil($剩余 / 86400) . "天）" : "❌ 已过期";
        $列表 .= "\n$id → $状态";
    }
    $this->发送('文本', $列表);
    return;
}
