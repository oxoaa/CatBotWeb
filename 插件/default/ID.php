<?php

if ($this->用户信息 == 'ID') {
    $文本 = "用户ID：{$this->用户ID}";

    if (in_array($this->事件类型, ["GROUP_AT_MESSAGE_CREATE", "GROUP_MESSAGE_CREATE"])) {
        $文本 .= "\n群聊ID：{$this->来源ID}";
    }

    $this->发送('文本', $文本);
}
