<?php
declare(strict_types=1);

namespace ShengBot\Core;

use ShengBot\Database\JsonDatabase;

class PluginContext
{
    public string $用户信息 = '';
    public string $用户ID = '';
    public string $来源ID = '';
    public string $信息ID = '';
    public string $事件类型 = '';
    public string $用户昵称 = '';
    public string $艾特用户 = '';
    public string $按钮数据 = '';
    public string $按钮ID = '';
    public string $事件ID = '';
    public array $当前账号 = [];
    public array $_responses = [];
    private ?JsonDatabase $_db = null;
    private string $_dbPath;

    public function __construct(array $params, string $dbPath = '')
    {
        foreach ($params as $k => $v) {
            if (property_exists($this, $k) && $k[0] !== '_') $this->$k = $v;
        }
        $this->_dbPath = $dbPath ?: (__DIR__ . '/../../数据/数据库');
    }

    public function 发送(string $类型, mixed $主内容 = null, mixed $附加1 = null, mixed $附加2 = null): ?string
    {
        $this->_responses[] = ['type' => $类型, 'content' => $主内容, 'extra1' => $附加1, 'extra2' => $附加2];
        return 'mock_' . count($this->_responses);
    }

    public function 数据库(string $操作, string $路径, mixed $数据 = null): mixed
    {
        if ($this->_db === null) $this->_db = new JsonDatabase($this->_dbPath);
        return ($this->_db)($操作, $路径, $数据);
    }

    public function 是管理员(): bool { return false; }
    public function 撤回(string $msgid = ''): bool { return false; }
    public function 获取上次发送ID(): ?string { return null; }
    public function 流式(int $状态, string $内容, ?string $流ID = null, int $序号 = 0, bool $重置 = false): ?string { return null; }
    public function 发送召回(string $内容): bool { return false; }
}

class PluginAPI
{
    private static array $tokenCache = [];

    public static function 处理(\Swoole\Http\Request $请求, \Swoole\Http\Response $响应, array $配置): void
    {
        $响应->header('Content-Type', 'application/json; charset=utf-8');
        $响应->header('Access-Control-Allow-Origin', '*');
        $响应->header('Access-Control-Allow-Headers', '*');
        $响应->header('Access-Control-Allow-Methods', 'POST, OPTIONS');
        if ($请求->getMethod() === 'OPTIONS') { $响应->status(204); $响应->end(); return; }

        $body = json_decode($请求->rawContent(), true) ?? [];
        $action = $body['action'] ?? '';
        switch ($action) {
            case 'list':   self::列表($响应, $body); break;
            case 'save':   self::保存($响应, $body); break;
            case 'delete': self::删除($响应, $body); break;
            case 'toggle': self::开关($响应, $body); break;
            case 'exec':   self::执行($响应, $body, $配置); break;
            default: $响应->end(json_encode(['code' => -1, 'msg' => '未知操作'], JSON_UNESCAPED_UNICODE));
        }
    }

    private static function 插件目录(string $appid): string { return __DIR__ . '/../../插件/' . $appid; }
    private static function 状态文件(string $appid): string { $d = __DIR__ . '/../../数据'; if (!is_dir($d)) mkdir($d, 0755, true); return $d . '/plugin_state_' . $appid . '.json'; }
    private static function 读取状态(string $appid): array { $f = self::状态文件($appid); return file_exists($f) ? (json_decode(file_get_contents($f), true) ?? []) : []; }
    private static function 保存状态(string $appid, array $s): void { file_put_contents(self::状态文件($appid), json_encode($s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); }

    private static function 列表(\Swoole\Http\Response $响应, array $body): void
    {
        $appid = (string)($body['appid'] ?? '');
        if (empty($appid)) { $响应->end(json_encode(['code' => -1, 'msg' => '缺少appid'], JSON_UNESCAPED_UNICODE)); return; }
        $dir = self::插件目录($appid); $st = self::读取状态($appid); $list = [];
        if (is_dir($dir)) { foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) { if ($f->isFile() && $f->getExtension() === 'php') { $n = $f->getBasename('.php'); $list[] = ['name' => $n, 'code' => file_get_contents($f->getPathname()), 'enabled' => $st[$n] ?? true]; } } }
        $响应->end(json_encode(['code' => 0, 'data' => $list], JSON_UNESCAPED_UNICODE));
    }

    private static function 保存(\Swoole\Http\Response $响应, array $body): void
    {
        $appid = (string)($body['appid'] ?? ''); $name = (string)($body['name'] ?? ''); $code = (string)($body['code'] ?? '');
        if (empty($appid) || empty($name) || empty($code)) { $响应->end(json_encode(['code' => -1, 'msg' => '参数不完整'], JSON_UNESCAPED_UNICODE)); return; }
        $dir = self::插件目录($appid); if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/' . $name . '.php', $code); PluginLoader::清除缓存();
        $响应->end(json_encode(['code' => 0, 'msg' => '保存成功'], JSON_UNESCAPED_UNICODE));
    }

    private static function 删除(\Swoole\Http\Response $响应, array $body): void
    {
        $appid = (string)($body['appid'] ?? ''); $name = (string)($body['name'] ?? '');
        if (empty($appid) || empty($name)) { $响应->end(json_encode(['code' => -1, 'msg' => '参数不完整'], JSON_UNESCAPED_UNICODE)); return; }
        $fp = self::插件目录($appid) . '/' . $name . '.php'; if (file_exists($fp)) unlink($fp);
        PluginLoader::清除缓存(); $响应->end(json_encode(['code' => 0, 'msg' => '删除成功'], JSON_UNESCAPED_UNICODE));
    }

    private static function 开关(\Swoole\Http\Response $响应, array $body): void
    {
        $appid = (string)($body['appid'] ?? ''); $name = (string)($body['name'] ?? ''); $en = (bool)($body['enabled'] ?? true);
        if (empty($appid) || empty($name)) { $响应->end(json_encode(['code' => -1, 'msg' => '参数不完整'], JSON_UNESCAPED_UNICODE)); return; }
        $st = self::读取状态($appid); $st[$name] = $en; self::保存状态($appid, $st); PluginLoader::清除缓存();
        $响应->end(json_encode(['code' => 0, 'msg' => $en ? '已启用' : '已禁用'], JSON_UNESCAPED_UNICODE));
    }

    private static function 获取令牌(array $botConfig): string
    {
        $appid = $botConfig['appid'];
        if (isset(self::$tokenCache[$appid]) && self::$tokenCache[$appid]['expires'] > time() + 60) {
            return self::$tokenCache[$appid]['token'];
        }
        try {
            $r = HttpClientPool::post('https://bots.qq.com/app/getAppAccessToken', json_encode([
                'appId' => (string)$appid,
                'clientSecret' => $botConfig['secret'],
            ], JSON_UNESCAPED_UNICODE), ['Content-Type' => 'application/json']);
            if ($r && $r['statusCode'] === 200) {
                $d = json_decode($r['body'], true);
                self::$tokenCache[$appid] = ['token' => $d['access_token'] ?? '', 'expires' => time() + (($d['expires_in'] ?? 7200) - 120)];
                return $d['access_token'] ?? '';
            }
        } catch (\Throwable $e) {}
        return '';
    }

    private static function 发送到QQ(array $botConfig, string $来源ID, string $事件类型, string $信息ID, array $responses): void
    {
        $token = self::获取令牌($botConfig);
        if (empty($token)) return;

        $sandbox = !empty($botConfig['sandbox']);
        $apiBase = $sandbox ? 'https://sandbox.api.sgroup.qq.com' : 'https://api.sgroup.qq.com';
        $appid = $botConfig['appid'];

        $url = match($事件类型) {
            'GROUP_AT_MESSAGE_CREATE', 'GROUP_MESSAGE_CREATE', 'GROUP_ADD_ROBOT', 'GROUP_DEL_ROBOT', 'GROUP_MEMBER_ADD', 'GROUP_MEMBER_REMOVE' => "{$apiBase}/v2/groups/{$来源ID}/messages",
            'C2C_MESSAGE_CREATE', 'FRIEND_ADD', 'FRIEND_DEL' => "{$apiBase}/v2/users/{$来源ID}/messages",
            default => null,
        };
        if (!$url) return;

        $headers = ['Content-Type' => 'application/json', 'Authorization' => 'QQBot ' . $token, 'X-Union-Appid' => $appid];

        foreach ($responses as $resp) {
            $type = $resp['type'] ?? '';
            $content = $resp['content'] ?? '';
            $extra1 = $resp['extra1'] ?? null;
            $extra2 = $resp['extra2'] ?? null;

            $data = ['msg_seq' => rand(1, 999999)];
            if (!empty($信息ID)) $data['msg_id'] = $信息ID;

            if ($type === '文本') {
                $data['content'] = (string)$content;
                $data['msg_type'] = 0;
            } elseif (in_array($type, ['md', 'MD', 'markdown', 'MarkDown'])) {
                $data['msg_type'] = 2;
                if ($content && !empty($content)) {
                    $data['markdown'] = ['custom_template_id' => $content, 'params' => $extra1 ?? []];
                } else {
                    $data['markdown'] = ['content' => $extra1 ?? ''];
                }
                if ($extra2) {
                    if (is_array($extra2)) {
                        if (isset($extra2['rows'])) $data['keyboard'] = ['content' => $extra2];
                        elseif (isset($extra2['content'])) $data['keyboard'] = ['content' => $extra2];
                        else $data['keyboard'] = ['id' => (string)$extra2];
                    } else {
                    }
                }
            } elseif ($type === '语音' || $type === 'voice') {
                // 语音消息通过文件上传接口发送（srv_send_msg=true直接发送）
                $fileUrl = $content ?: ($extra1 ?? '');
                if (empty($fileUrl)) continue;
                $filesUrl = match($事件类型) {
                    'GROUP_AT_MESSAGE_CREATE', 'GROUP_MESSAGE_CREATE', 'GROUP_ADD_ROBOT', 'GROUP_DEL_ROBOT', 'GROUP_MEMBER_ADD', 'GROUP_MEMBER_REMOVE' => "{$apiBase}/v2/groups/{$来源ID}/files",
                    'C2C_MESSAGE_CREATE', 'FRIEND_ADD', 'FRIEND_DEL' => "{$apiBase}/v2/users/{$来源ID}/files",
                    default => null,
                };
                if ($filesUrl) {
                    $fileData = ['file_type' => 3, 'url' => $fileUrl, 'srv_send_msg' => true];
                    if (!empty($信息ID)) $fileData['msg_id'] = $信息ID;
                    try {
                        HttpClientPool::post($filesUrl, json_encode($fileData, JSON_UNESCAPED_UNICODE), $headers);
                    } catch (\Throwable $e) {}
                }
                continue;
            } else {
                continue;
            }

            try {
                $r = HttpClientPool::post($url, json_encode($data, JSON_UNESCAPED_UNICODE), $headers);
            } catch (\Throwable $e) {}
        }
    }

    private static function 执行(\Swoole\Http\Response $响应, array $body, array $配置): void
    {
        $appid = (string)($body['appid'] ?? '');
        $消息 = (string)($body['message'] ?? '');
        $用户ID = (string)($body['user_id'] ?? '');
        $来源ID = (string)($body['source_id'] ?? '');
        $事件类型 = (string)($body['event_type'] ?? 'GROUP_AT_MESSAGE_CREATE');
        $信息ID = (string)($body['msg_id'] ?? '');

        if (empty($appid) || empty($消息)) {
            $响应->end(json_encode(['code' => -1, 'msg' => '参数不完整'], JSON_UNESCAPED_UNICODE));
            return;
        }

        $响应->end(json_encode(['code' => 0, 'msg' => 'ok'], JSON_UNESCAPED_UNICODE));

        \Swoole\Coroutine\go(function() use ($appid, $消息, $用户ID, $来源ID, $事件类型, $信息ID, $body, $配置) {
            $dbPath = __DIR__ . '/../../数据/数据库';
            $ctx = new PluginContext([
                '用户信息' => $消息, '用户ID' => $用户ID, '来源ID' => $来源ID,
                '信息ID' => $信息ID, '事件类型' => $事件类型,
                '用户昵称' => (string)($body['nickname'] ?? ''),
                '当前账号' => ['appid' => (int)$appid],
            ], $dbPath);

            $目录 = self::插件目录($appid);
            if (!is_dir($目录)) return;

            $状态 = self::读取状态($appid);
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($目录, \FilesystemIterator::SKIP_DOTS)) as $文件) {
                if (!$文件->isFile() || $文件->getExtension() !== 'php') continue;
                $名称 = $文件->getBasename('.php');
                if (isset($状态[$名称]) && $状态[$名称] === false) continue;
                try {
                    $闭包 = \Closure::bind(fn() => require $文件, $ctx, PluginContext::class);
                    $闭包();
                } catch (\Throwable $e) {}
            }

            if (empty($ctx->_responses)) return;

            $botConfig = null;
            foreach (($配置['框架']['QQBOT'] ?? []) as $bot) {
                if ((string)$bot['appid'] === $appid) { $botConfig = $bot; break; }
            }
            if (!$botConfig) return;

            self::发送到QQ($botConfig, $来源ID, $事件类型, $信息ID, $ctx->_responses);
        });
    }
}
