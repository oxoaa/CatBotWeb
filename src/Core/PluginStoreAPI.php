<?php
declare(strict_types=1);

namespace ShengBot\Core;

class PluginStoreAPI
{
    private static function 仓库目录(): string { return __DIR__ . '/../../插件仓库'; }
    private static function 管理员列表(): array { return ['102348715']; }

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
            case 'store_edit':   self::编辑($响应, $body); break;
            case 'store_delete': self::删除($响应, $body); break;
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
        $authorName = trim((string)($body['author_name'] ?? ''));
        $code = (string)($body['code'] ?? '');
        if (empty($name) || empty($authorName) || empty($code)) {
            $响应->end(json_encode(['code' => -1, 'msg' => '缺少插件名称、作者名称或代码'], JSON_UNESCAPED_UNICODE));
            return;
        }
        $dir = self::仓库目录();
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $id = md5($name . time());
        $item = [
            'id' => $id,
            'name' => $name,
            'author_name' => $authorName,
            'code' => $code,
            'time' => time(),
        ];
        file_put_contents($dir . '/' . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $响应->end(json_encode(['code' => 0, 'msg' => '上传成功'], JSON_UNESCAPED_UNICODE));
    }

    private static function 编辑(\Swoole\Http\Response $响应, array $body): void
    {
        $operatorAppid = (string)($body['operator_appid'] ?? '');
        if (!in_array($operatorAppid, self::管理员列表())) {
            $响应->end(json_encode(['code' => 403, 'msg' => '无权限，仅管理员可编辑插件'], JSON_UNESCAPED_UNICODE));
            return;
        }

        $name = (string)($body['name'] ?? '');
        $authorName = trim((string)($body['author_name'] ?? ''));
        $code = (string)($body['code'] ?? '');
        if (empty($name)) {
            $响应->end(json_encode(['code' => -1, 'msg' => '缺少插件名称'], JSON_UNESCAPED_UNICODE));
            return;
        }

        $dir = self::仓库目录();
        $target = null;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') as $f) {
                $item = json_decode(file_get_contents($f), true);
                if ($item && ($item['name'] ?? '') === $name) { $target = $f; $existing = $item; break; }
            }
        }
        if (!$target) {
            $响应->end(json_encode(['code' => 404, 'msg' => '插件不存在'], JSON_UNESCAPED_UNICODE));
            return;
        }

        if (!empty($authorName)) $existing['author_name'] = $authorName;
        if (!empty($code)) $existing['code'] = $code;
        $existing['time'] = time();

        file_put_contents($target, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $响应->end(json_encode(['code' => 0, 'msg' => '编辑成功'], JSON_UNESCAPED_UNICODE));
    }

    private static function 删除(\Swoole\Http\Response $响应, array $body): void
    {
        $operatorAppid = (string)($body['operator_appid'] ?? '');
        if (!in_array($operatorAppid, self::管理员列表())) {
            $响应->end(json_encode(['code' => 403, 'msg' => '无权限，仅管理员可删除插件'], JSON_UNESCAPED_UNICODE));
            return;
        }

        $name = (string)($body['name'] ?? '');
        if (empty($name)) {
            $响应->end(json_encode(['code' => -1, 'msg' => '缺少插件名称'], JSON_UNESCAPED_UNICODE));
            return;
        }

        $dir = self::仓库目录();
        $deleted = false;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') as $f) {
                $item = json_decode(file_get_contents($f), true);
                if ($item && ($item['name'] ?? '') === $name) {
                    unlink($f);
                    $deleted = true;
                    break;
                }
            }
        }

        if ($deleted) {
            $响应->end(json_encode(['code' => 0, 'msg' => '删除成功'], JSON_UNESCAPED_UNICODE));
        } else {
            $响应->end(json_encode(['code' => 404, 'msg' => '插件不存在'], JSON_UNESCAPED_UNICODE));
        }
    }
}
