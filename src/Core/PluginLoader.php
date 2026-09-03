<?php
declare(strict_types=1);

namespace ShengBot\Core;

use Closure;

class PluginLoader
{
    private static array $文件列表缓存 = [];

    public static function 加载(string $目录, object $上下文, ?Logger $logger = null): void
    {
        if (!is_dir($目录)) return;

        $appid = $上下文->当前账号['appid'] ?? '';
        $状态 = [];
        if ($appid) {
            $状态文件 = dirname($目录) . '/数据/plugin_state_' . $appid . '.json';
            if (file_exists($状态文件)) {
                $状态 = json_decode(file_get_contents($状态文件), true) ?? [];
            }
        }

        if (!isset(self::$文件列表缓存[$目录])) {
            $文件列表 = [];
            $目录迭代器 = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($目录, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($目录迭代器 as $文件信息) {
                if ($文件信息->isFile() && $文件信息->getExtension() === 'php') {
                    $文件列表[] = $文件信息->getPathname();
                }
            }
            self::$文件列表缓存[$目录] = $文件列表;
        }

        foreach (self::$文件列表缓存[$目录] as $文件) {
            $名称 = basename($文件, '.php');
            if (isset($状态[$名称]) && $状态[$名称] === false) {
                continue;
            }
            try {
                $闭包 = Closure::bind(function () use ($文件) {
                    require $文件;
                }, $上下文, get_class($上下文));
                $闭包();
            } catch (\Throwable $e) {
                $消息 = "[插件加载错误] {$文件}: " . $e->getMessage();
                if ($logger) {
                    $logger->error($消息);
                } else {
                    echo $消息 . PHP_EOL;
                }
            }
        }
    }

    public static function 清除缓存(): void
    {
        self::$文件列表缓存 = [];
    }
}
