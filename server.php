<?php
date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/vendor/autoload.php';

use Swoole\Coroutine\Http\Server;
use Swoole\Coroutine;
use Swoole\Runtime;
use Swoole\Process;
use ShengBot\Core\Router;
use ShengBot\Core\Logger;
use ShengBot\Core\HttpClientPool;

if (!file_exists(__DIR__ . '/config.json')) {
    exit("配置文件不存在: config.json\n");
}

if (version_compare(PHP_VERSION, '8.4.0', '<')) {
    exit('PHP版本需要8.4+，当前：' . PHP_VERSION . "\n");
}

if (!extension_loaded('swoole')) {
    exit("Swoole扩展未安装\n");
}

$配置 = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
if (!$配置) exit("配置 JSON 解析失败\n");

foreach (['域名', 'http端口', '框架'] as $f) {
    if (!isset($配置[$f])) exit("配置缺少: {$f}\n");
}

Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

Coroutine\run(function () use ($配置) {
    $logger = new Logger();

    HttpClientPool::配置(
        $配置['连接池大小'] ?? 8,
        $配置['连接超时'] ?? 10
    );

    $回调 = function (\Swoole\Http\Request $请求, \Swoole\Http\Response $响应) use ($配置, $logger) {
        try {
            Router::分发($请求, $响应, $配置);
        } catch (Throwable $e) {
            $logger->error("[请求异常] " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $响应->status(500);
            $响应->end("Internal Server Error");
        }
    };

    // 重启标记处理
    $重启标记 = __DIR__ . '/数据/重启标记.json';
    if (file_exists($重启标记)) {
        $标记 = json_decode(file_get_contents($重启标记), true);
        unlink($重启标记);
        $耗时 = round(microtime(true) - $标记['时间'], 2);
        echo "✅ 重启成功 PID=" . getmypid() . " 耗时 {$耗时}s\n";
    }

    // 启动 HTTP 服务
    go(function () use ($配置, $回调) {
        $srv = new Server($配置['域名'], $配置['http端口'], false);
        $srv->handle('/', $回调);
        echo "✅ Bot 启动 http://{$配置['域名']}:{$配置['http端口']}\n";
        $srv->start();
    });

    // 优雅退出
    $shouldExit = false;
    Process::signal(SIGTERM, function () use (&$shouldExit) { $shouldExit = true; });
    Process::signal(SIGINT, function () use (&$shouldExit) { $shouldExit = true; });

    go(function () use (&$shouldExit) {
        while (!$shouldExit) Coroutine::sleep(0.5);
        echo "已关闭\n";
        posix_kill(getmypid(), SIGKILL);
    });
});
