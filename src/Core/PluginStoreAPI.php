<?php
declare(strict_types=1);

namespace ShengBot\Core;

class PluginStoreAPI
{
    private static function 仓库目录(): string { return __DIR__ . '/../../插件仓库'; }

    public static function 处理(\Swoole\Http\Request $请求, \Swoole\Http\Response $响应): void
    {
        $响应->header('Content-Type', 'application/json; charset=utf-8');
        $响应->header('Access-Control-Allow-Origin', '*');
        $响应->header('Access-Control-Allow-Headers', '*');
        $响应->header('Access-Control-Allow-Methods', 'POST, OPTIONS');
        if ($请求->getMethod() === 'OPTIONS') { $响应->status(204); $响应->end(); return; }

        $body = json_decode($请求->rawContent(), true) ?? [];
        $action = $body['action'] ?? '';
        switch ($action) {
            case 'store_list':   self::列表($响应); break;
            case 'store_upload': self::上传($响应, $body); break;
            default: $响应->end(json_encode(['code' => -1, 'msg' => '未知操作'], JSON_UNESCAPED_UNICODE));
        }
    }

    private static function 列表(\Swoole\Http\Response $响应): void
    {
        $dir = self::仓库目录();
        $list = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') as $f) {
                $item = json_decode(file_get_contents($f), true);
                if ($item) $list[] = $item;
            }
            usort($list, fn($a, $b) => ($b['time'] ?? 0) - ($a['time'] ?? 0));
        }
        $响应->end(json_encode(['code' => 0, 'data' => $list], JSON_UNESCAPED_UNICODE));
    }

    private static function 上传(\Swoole\Http\Response $响应, array $body): void
    {
        $name = (string)($body['name'] ?? '');
        $code = (string)($body['code'] ?? '');
        $authorQQ = (string)($body['author_qq'] ?? '');
        $authorName = (string)($body['author_name'] ?? '');
        $authorLink = (string)($body['author_link'] ?? '');
        if (empty($name) || empty($code)) {
            $响应->end(json_encode(['code' => -1, 'msg' => '缺少插件名称或代码'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $dir = self::仓库目录();
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $id = md5($name . $authorQQ . time());
        $item = [
            'id' => $id,
            'name' => $name,
            'code' => $code,
            'author_qq' => $authorQQ,
            'author_name' => $authorName,
            'author_link' => $authorLink,
            'time' => time(),
        ];
        file_put_contents($dir . '/' . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $响应->end(json_encode(['code' => 0, 'msg' => '上传成功'], JSON_UNESCAPED_UNICODE));
    }
}
