
if (!function_exists("_gameAt")) {
function _gameAt(string $id): string {
    return empty($id) ? "" : "<@" . $id . ">";
}
}
<?php
/**
 * 文字找茬插件 v16 - 回调按钮(type=1)，任何人都能参与
 */

$_dataDir = __DIR__ . "/../../数据";
if (!is_dir($_dataDir)) { @mkdir($_dataDir, 0755, true); }
$_stateFile = $_dataDir . "/find_diff.json";

$_fd_read = function($f) {
    $fp = @fopen($f, "r");
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $c = fread($fp, filesize($f) ?: 1);
    flock($fp, LOCK_UN);
    fclose($fp);
    return json_decode($c ?: "{}", true) ?: [];
};

$_fd_write = function($f, $d) {
    $fp = @fopen($f, "c");
    if (!$fp) return;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
};

$_fd_fetch = function() {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://oiapi.net/api/ChineseFindDifferent?x=5&y=5",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $d = json_decode($resp, true);
    return ($d && $d["code"] == 1) ? $d : null;
};

$_fd_parse = function($data) {
    $rows = explode("\n", $data["data"]["result"]);
    $y = $data["data"]["y"];
    $diffRow = mb_str_split($rows[$y - 1]);
    $allChars = [];
    foreach ($rows as $r) { foreach (mb_str_split($r) as $c) { $allChars[] = $c; } }
    $freq = array_count_values($allChars);
    arsort($freq);
    $majority = array_key_first($freq);
    $diffCol = null;
    foreach ($diffRow as $col => $ch) {
        if ($ch !== $majority) { $diffCol = $col + 1; break; }
    }
    return [$diffCol, $y, $rows];
};

$_fd_kb = function($rows) {
    $kb = ["rows" => []];
    foreach ($rows as $r => $rowText) {
        $btns = [];
        $chars = mb_str_split($rowText);
        foreach ($chars as $c => $ch) {
            $btns[] = [
                "id" => "fd{$r}_{$c}",
                "render_data" => ["label" => $ch, "style" => 1],
                "action" => [
                    "type" => 1,
                    "permission" => ["type" => 2],
                    "data" => "fd:{$r},{$c}",
                ]
            ];
        }
        $kb["rows"][] = ["buttons" => $btns];
    }
    return $kb;
};

// ===== 统一入口 =====
$_uid = (string)($this->用户ID ?? "");
$_src = (string)($this->来源ID ?? "");
$_msg = '';

if (in_array($this->事件类型, ["C2C_MESSAGE_CREATE", "GROUP_AT_MESSAGE_CREATE", "GROUP_MESSAGE_CREATE"])) {
    $_msg = trim($this->用户信息);
} elseif ($this->事件类型 === "INTERACTION_CREATE" && !empty($this->按钮数据)) {
    $_msg = trim($this->按钮数据);
} else {
    return;
}

// ===== 新游戏（谁发文字找茬就给谁开，按群/频道隔离） =====
if ($_msg === "文字找茬") {
    $_data = $_fd_fetch();
    if (!$_data) { $this->发送("md", null, "<@" . $_uid . "> " . "获取文字找茬数据失败，请稍后再试"); return; }

    [$_diffCol, $_diffY, $_rows] = $_fd_parse($_data);
    $_state = $_fd_read($_stateFile);
    $_state[$_src] = ["ans_x" => $_diffCol, "ans_y" => $_diffY, "round" => ($_state[$_src]["round"] ?? 0) + 1];
    // 记录房间归属
    $_state["_room_" . $_src] = $_uid;
    $_fd_write($_stateFile, $_state);

    $this->发送("md", null, "<@" . $_uid . "> 请找出不同的字叭", $_fd_kb($_rows));
    return;
}

// ===== 按钮点击 "fd:r,c" =====
if (strpos($_msg, "fd:") === 0) {
    $__p = explode(",", substr($_msg, 3));
    if (count($__p) < 2) return;
    
    // 验证房间归属
    $fdState = $_fd_read($_stateFile);
    $roomOwner = $fdState["_room_" . $_src] ?? "";
    if ($roomOwner && $roomOwner !== $_uid) {
        $this->发送("md", null, "<@" . $_uid . "> " . "❌ 这不是你的游戏哦～ 「文字找茬」你自己的！");
        return;
    }
    if (count($__p) < 2) return;
    $__row = intval($__p[0]) + 1;
    $__col = intval($__p[1]) + 1;

    $__state = $_fd_read($_stateFile);
    $__gs = $__state[$_src] ?? null;

    // 无状态 → 新一题
    if (!$__gs || !isset($__gs["ans_x"]) || !isset($__gs["ans_y"])) {
        $__data = $_fd_fetch();
        if (!$__data) return;
        [$__gs["ans_x"], $__gs["ans_y"], $__rows] = $_fd_parse($__data);
        $__gs["round"] = ($__gs["round"] ?? 0) + 1;
        $__state[$_src] = $__gs;
        $_fd_write($_stateFile, $__state);

        $this->发送("md", null, "<@" . $_uid . "> 请找出不同的字叭", $_fd_kb($__rows));
        return;
    }

    // 判断对错
    if ($__row === $__gs["ans_y"] && $__col === $__gs["ans_x"]) {
        // 答对
        $__round = $__gs["round"];
        unset($__state[$_src]);
        $_fd_write($_stateFile, $__state);

        $this->发送("md", null, "<@" . $_uid . "> 我超，好牛逼，等会我再给你整一个！");

        // 自动下一题
        $__data = $_fd_fetch();
        if ($__data) {
            [$__dc, $__dy, $__rows2] = $_fd_parse($__data);
            $__state[$_src] = ["ans_x" => $__dc, "ans_y" => $__dy, "round" => $__round + 1];
            $_fd_write($_stateFile, $__state);
            $this->发送("md", null, "<@" . $_uid . "> 请找出不同的字叭", $_fd_kb($__rows2));
        }
    } else {
        // 答错
        $this->发送("md", null, "<@" . $_uid . "> 眼睛不要就捐给有需要的人吧？");
    }
    return;
}
