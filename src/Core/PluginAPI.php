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
        $this->_responses[] = [
            'type' => $类型,
            'content' => $主内容,
            'extra1' => $附加1,
            'extra2' => $附加2,
        ];
        return 'mock_' . count($this->_responses);
    }

    public function 数据库(string $操作, string $路径, mixed $数据 = null): mixed
    {
        if ($this->_db === null) {
            $this->_db = new JsonDatabase($this->_dbPath);
        }
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
    public static function 处理(\Swoole\Http\Request $请求, \Swoole\Http\Response $响应, array $配置): void
    {
        $响应->header('Content-Type', 'application/json; charset=utf-8');
        $响应->header('Access-Control-Allow-Origin', '*');
        $响应->header('Access-Control-Allow-Headers', '*');
        $响应->header('Access-Control-Allow-Methods', 'POST, OPTIONS');

        if ($请求->getMethod() === 'OPTIONS') {
            $响应->status(204);
            $响应->end();
            return;
        }

        $body = json_decode($请求->rawContent(), true) ?? [];
        $action = $body['action'] ?? '';

        switch ($action) {
            case 'list':   self::列表($响应, $body); break;
            case 'save':   self::保存($响应, $body); break;
            case 'delete': self::删除($响应, $body); break;
            case 'toggle': self::开关($响应, $body); break;
            case 'exec':   self::执行($响应, $body); break;
            default:
                $响应->end(json_encode(['code' => -1, 'msg' => '未知操作'], JSON_UNESCAPED_UNICODE));
        }
    }

    private static function 插件目录(string $appid): string
    {
        return __DIR__ . '/../../插件/' . $appid;
    }

    private static function 状态文件(string $appid): string
    {
        $dir = __DIR__ . '/../../数据';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir . '/plugin_state_' . $appid . '.json';
    }

    private static function 读取状态(string $appid): array
    {
        $file = self::状态文件($appid);
        if (!file_exists($file)) return [];
        return json_decode(file_get_contents($file), true) ?? [];
    }

    private static function 保存状态(string $appid, array $状态): void
    {
        file_put_contents(self::状态文件($appid), json_encode($状态, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private static function 列表(\Swoole\Http\Response $响应, array $body): void
    {
        $appid = (string)($body['appid'] ?? '');
        if (empty($appid)) {
            $响应->end(json_encode(['code' => -1, 'msg' => '缺少appid'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $目录 = self::插件目录($appid);
        $状态 = self::读取状态($appid);
        $插件列表 = [];
        if (is_dir($目录)) {
            $迭代器 = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($目录, \FilesystemIterator::SKIP_DOTS));
            foreach ($迭代器 as $文件) {
                if ($文件->isFile() && $文件->getExtension() === 'php') {
                    $名称 = $文件->getBasename('.php');
                    $插件列表[] = ['name' => $名称, 'code' => file_get_contents($文件->getPathname()), 'enabled' => $状态[$名称] ?? true];
                }
            }
        }
        $响应->end(json_encode(['code' => 0, 'data' => $插件列表], JSON_UNESCAPED_UNICODE));
    }

    private static function 保存(\Swoole\Http\Response $响应, array $body): void
    {
        $appid = (string)($body['appid'] ?? '');
        $名称 = (string)($body['name'] ?? '');
        $代码 = (string)($body['code'] ?? '');
        if (empty($appid) || empty($名称) || empty($代码)) {
            $响应->end(json_encode(['code' => -1, 'msg' => '参数不完整'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $目录 = self::插件目录($appid);
        if (!is_dir($目录)) mkdir($目录, 0755, true);
        file_put_contents($目录 . '/' . $名称 . '.php', $代码);
        PluginLoader::清除缓存();
        $响应->end(json_encode(['code' => 0, 'msg' => '保存成功'], JSON_UNESCAPED_UNICODE));
    }

    private static function 删除(\Swoole\Http\Response $响应, array $body): void
    {
        $appid = (string)($body['appid'] ?? '');
        $名称 = (string)($body['name'] ?? '');
        if (empty($appid) || empty($名称)) {
            $响应->end(json_encode(['code' => -1, 'msg' => '参数不完整'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $文件路径 = self::插件目录($appid) . '/' . $名称 . '.php';
        if (file_exists($文件路径)) unlink($文件路径);
        PluginLoader::清除缓存();
        $响应->end(json_encode(['code' => 0, 'msg' => '删除成功'], JSON_UNESCAPED_UNICODE));
    }

    private static function 开关(\Swoole\Http\Response $响应, array $body): void
    {
        $appid = (string)($body['appid'] ?? '');
        $名称 = (string)($body['name'] ?? '');
        $enabled = (bool)($body['enabled'] ?? true);
        if (empty($appid) || empty($名称)) {
            $响应->end(json_encode(['code' => -1, 'msg' => '参数不完整'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $状态 = self::读取状态($appid);
        $状态[$名称] = $enabled;
        self::保存状态($appid, $状态);
        PluginLoader::清除缓存();
        $响应->end(json_encode(['code' => 0, 'msg' => $enabled ? '已启用' : '已禁用'], JSON_UNESCAPED_UNICODE));
    }

    private static function 执行(\Swoole\Http\Response $响应, array $body): void
    {
        $appid = (string)($body['appid'] ?? '');
        $消息 = (string)($body['message'] ?? '');
        $用户ID = (string)($body['user_id'] ?? '');
        $来源ID = (string)($body['source_id'] ?? '');
        $事件类型 = (string)($body['event_type'] ?? 'GROUP_AT_MESSAGE_CREATE');

        if (empty($appid) || empty($消息)) {
            $响应->end(json_encode(['code' => -1, 'msg' => '参数不完整'], JSON_UNESCAPED_UNICODE));
            return;
        }

        $dbPath = __DIR__ . '/../../数据/数据库';
        $ctx = new PluginContext([
            '用户信息' => $消息,
            '用户ID' => $用户ID,
            '来源ID' => $来源ID,
            '信息ID' => (string)($body['msg_id'] ?? ''),
            '事件类型' => $事件类型,
            '用户昵称' => (string)($body['nickname'] ?? ''),
            '当前账号' => ['appid' => (int)$appid],
        ], $dbPath);

        $目录 = self::插件目录($appid);
        if (!is_dir($目录)) {
            $响应->end(json_encode(['code' => 0, 'data' => ['responses' => []]], JSON_UNESCAPED_UNICODE));
            return;
        }

        $状态 = self::读取状态($appid);
        $迭代器 = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($目录, \FilesystemIterator::SKIP_DOTS));

        foreach ($迭代器 as $文件) {
            if (!$文件->isFile() || $文件->getExtension() !== 'php') continue;
            $名称 = $文件->getBasename('.php');
            if (isset($状态[$名称]) && $状态[$名称] === false) continue;
            try {
                $闭包 = \Closure::bind(function() use ($文件) {
                    require $文件;
                }, $ctx, PluginContext::class);
                $闭包();
            } catch (\Throwable $e) {
                // 忽略单个插件错误
            }
        }

        $响应->end(json_encode(['code' => 0, 'data' => ['responses' => $ctx->_responses]], JSON_UNESCAPED_UNICODE));
    }
}
