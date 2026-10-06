<?php
declare(strict_types=1);

namespace ShengBot\Core;

/**
 * 抖音视频与图集去水印解析接口 (Swoole 异步/协程与 HTTP 控制器)
 */
class DouyinAPI
{
    public static function 处理(\Swoole\Http\Request $请求, \Swoole\Http\Response $响应): void
    {
        // 允许跨域
        $响应->header('Access-Control-Allow-Origin', '*');
        $响应->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $响应->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $响应->header('Content-Type', 'application/json; charset=utf-8');

        if ($请求->getMethod() === 'OPTIONS') {
            $响应->status(204);
            $响应->end();
            return;
        }

        // 提取传入链接参数 (GET/POST/JSON)
        $url = null;
        if (!empty($请求->get['url'])) {
            $url = trim((string)$请求->get['url']);
        } elseif (!empty($请求->post['url'])) {
            $url = trim((string)$请求->post['url']);
        } else {
            $body = $请求->rawContent();
            if (!empty($body)) {
                $json = json_decode($body, true);
                if (!empty($json['url'])) {
                    $url = trim((string)$json['url']);
                } elseif (str_starts_with(trim($body), 'http')) {
                    $url = trim($body);
                }
            }
        }

        if (empty($url)) {
            $uri = $请求->server['request_uri'] ?? '';
            if (($pos = strpos($uri, 'url=')) !== false) {
                $raw = substr($uri, $pos + 4);
                $url = trim(urldecode($raw));
            }
        }

        if (empty($url)) {
            $响应->status(200);
            $响应->end(json_encode([
                'code' => 400,
                'msg'  => '缺少必要参数: 请传入抖音分享链接 (GET/POST url 参数或 JSON {"url": "..."})',
                'data' => null
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }

        // 执行解析
        $parser = new DouyinParser();
        $result = $parser->parse($url);

        $响应->status(200);
        $响应->end(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

/**
 * 抖音视频去水印底层核心解析器
 */
class DouyinParser
{
    private string $mobileUA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1';
    private string $desktopUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    public function parse(string $text): array
    {
        // 1. 从文字中提取链接
        $shareUrl = $this->extractUrlFromText($text);
        if (!$shareUrl) {
            return $this->errorResponse(400, '未能从输入文本中识别出有效的 URL 链接');
        }

        // 2. 跟踪重定向获取最终长链接
        $finalUrl = $this->resolveFinalUrl($shareUrl);
        if (!$finalUrl) {
            $finalUrl = $shareUrl;
        }

        // 3. 提取作品 ID
        $itemId = $this->extractItemId($finalUrl);

        // 4. 策略 A: 官方 SSR 数据主通道解析
        if ($itemId) {
            $dataA = $this->parseByOfficialPage($itemId);
            if ($dataA) {
                return $this->successResponse($dataA);
            }
        }

        // 策略 B: 官方移动接口通道
        if ($itemId) {
            $dataB = $this->parseByOfficialApi($itemId);
            if ($dataB) {
                return $this->successResponse($dataB);
            }
        }

        // 策略 C: 智能备用容灾通道 (防机房 IP 被官方 WAF 拦截)
        $dataC = $this->parseByFallbackGateway($shareUrl);
        if ($dataC) {
            return $this->successResponse($dataC);
        }

        return $this->errorResponse(500, '解析失败: 视频可能已被作者删除、设为私密，或当前机房 IP 触发了官方防爬拦截');
    }

    private function extractUrlFromText(string $text): ?string
    {
        if (preg_match('/https?:\/\/[a-zA-Z0-9_\-\.\/\?=&%#]+/i', $text, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function resolveFinalUrl(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_USERAGENT      => $this->mobileUA,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_MAXREDIRS      => 6,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_NOBODY         => true,
        ]);
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        if (PHP_VERSION_ID < 80500) { @curl_close($ch); }

        return !empty($finalUrl) ? $finalUrl : null;
    }

    private function extractItemId(string $url): ?string
    {
        $patterns = [
            '/\/video\/(\d+)/',
            '/\/note\/(\d+)/',
            '/\/share\/video\/(\d+)/',
            '/\/share\/slides\/(\d+)/',
            '/modal_id=(\d+)/',
            '/item_id=(\d+)/',
            '/(\d{18,20})/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    private function parseByOfficialPage(string $itemId): ?array
    {
        $url = "https://www.iesdouyin.com/share/video/{$itemId}/";
        $html = $this->httpGet($url, [
            'User-Agent: ' . $this->mobileUA,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9',
            'Referer: https://www.douyin.com/'
        ]);

        if (empty($html)) return null;

        $item = null;
        if (preg_match('/window\._ROUTER_DATA\s*=\s*(.*?);?\s*<\/script>/s', $html, $m)) {
            $json = json_decode(trim($m[1]), true);
            if (!empty($json['loaderData'])) {
                foreach ($json['loaderData'] as $k => $v) {
                    if (str_starts_with((string)$k, 'video_') && !empty($v['videoInfoRes']['item_list'][0])) {
                        $item = $v['videoInfoRes']['item_list'][0];
                        break;
                    }
                }
            }
        }

        if (!$item && preg_match('/<script id="RENDER_DATA"[^>]*>(.*?)<\/script>/s', $html, $m)) {
            $decoded = urldecode($m[1]);
            $json = json_decode($decoded, true);
            if (!empty($json['app']['videoDetail'])) {
                $item = $json['app']['videoDetail'];
            }
        }

        if (!$item) return null;

        return $this->formatDouyinItem($item, $itemId);
    }

    private function parseByOfficialApi(string $itemId): ?array
    {
        $url = "https://www.iesdouyin.com/web/api/v2/aweme/iteminfo/?item_ids={$itemId}";
        $res = $this->httpGet($url, [
            'User-Agent: ' . $this->mobileUA,
            'Referer: https://www.iesdouyin.com/'
        ]);

        if (empty($res)) return null;
        $json = json_decode($res, true);
        if (empty($json['item_list'][0])) return null;

        return $this->formatDouyinItem($json['item_list'][0], $itemId);
    }

    private function parseByFallbackGateway(string $shareUrl): ?array
    {
        $endpoints = [
            'https://api.s01s.cn/API/sp_jx/?url=' . urlencode($shareUrl),
            'https://api.pearktrue.cn/api/video/douyin/?url=' . urlencode($shareUrl),
        ];

        foreach ($endpoints as $api) {
            $res = $this->httpGet($api, ['User-Agent: ' . $this->desktopUA], 6);
            if (empty($res)) continue;

            $json = json_decode($res, true);
            if (empty($json)) continue;

            $video = $json['data']['video'] ?? $json['data']['url'] ?? $json['data']['video_url'] ?? $json['video'] ?? $json['url'] ?? '';
            $title = $json['data']['title'] ?? $json['data']['desc'] ?? $json['title'] ?? $json['desc'] ?? '';
            $cover = $json['data']['cover'] ?? $json['cover'] ?? '';
            $images = $json['data']['images'] ?? $json['images'] ?? [];

            if (!empty($video) || !empty($images)) {
                return [
                    'type'       => !empty($images) ? 'image' : 'video',
                    'item_id'    => $this->extractItemId($shareUrl) ?? '',
                    'title'      => (string)$title,
                    'author'     => [
                        'nickname' => $json['data']['author'] ?? $json['author'] ?? '抖音用户',
                        'uid'      => $json['data']['uid'] ?? '',
                        'avatar'   => $json['data']['avatar'] ?? '',
                    ],
                    'cover'      => (string)$cover,
                    'video_url'  => !empty($video) ? (string)$video : null,
                    'music'      => [
                        'title'  => $json['data']['music']['title'] ?? '原声音乐',
                        'author' => $json['data']['music']['author'] ?? '',
                        'url'    => $json['data']['music']['url'] ?? null,
                    ],
                    'images'     => is_array($images) ? array_values($images) : []
                ];
            }
        }

        return null;
    }

    private function formatDouyinItem(array $item, string $itemId): array
    {
        $desc = $item['desc'] ?? '';
        $author = [
            'nickname' => $item['author']['nickname'] ?? '抖音用户',
            'uid'      => (string)($item['author']['unique_id'] ?? $item['author']['uid'] ?? ''),
            'avatar'   => $item['author']['avatar_thumb']['url_list'][0] ?? ($item['author']['avatar_medium']['url_list'][0] ?? ''),
        ];

        $cover = $item['video']['origin_cover']['url_list'][0] 
              ?? $item['video']['cover']['url_list'][0] 
              ?? ($item['video']['dynamic_cover']['url_list'][0] ?? '');

        $music = [
            'title'  => $item['music']['title'] ?? '原声音乐',
            'author' => $item['music']['author'] ?? ($author['nickname'] ?? ''),
            'url'    => $item['music']['play_url']['url_list'][0] ?? null,
        ];

        $imagesList = [];
        if (!empty($item['images']) && is_array($item['images'])) {
            foreach ($item['images'] as $img) {
                $imgUrl = $img['url_list'][0] ?? ($img['urlList'][0] ?? null);
                if ($imgUrl) {
                    $imagesList[] = $imgUrl;
                }
            }
        }

        if (!empty($imagesList)) {
            return [
                'type'       => 'image',
                'item_id'    => $itemId,
                'title'      => $desc,
                'author'     => $author,
                'cover'      => $cover ?: ($imagesList[0] ?? ''),
                'video_url'  => null,
                'music'      => $music,
                'images'     => $imagesList
            ];
        }

        $rawPlayUrl = $item['video']['play_addr']['url_list'][0] ?? null;
        $videoUrl = null;

        if ($rawPlayUrl) {
            $videoUrl = str_replace('playwm', 'play', $rawPlayUrl);
        }

        $vid = $item['video']['play_addr']['uri'] ?? null;
        if ($vid) {
            $originalEndpoint = "https://aweme.snssdk.com/aweme/v1/play/?video_id=" . rawurlencode((string)$vid) . "&ratio=default&line=0";
            $cdnUrl = $this->resolveFinalUrl($originalEndpoint);
            if ($cdnUrl && !str_contains($cdnUrl, 'aweme.snssdk.com')) {
                $videoUrl = $cdnUrl;
            } else {
                $videoUrl = $videoUrl ?: $originalEndpoint;
            }
        }

        return [
            'type'       => 'video',
            'item_id'    => $itemId,
            'title'      => $desc,
            'author'     => $author,
            'cover'      => $cover,
            'video_url'  => $videoUrl,
            'music'      => $music,
            'images'     => []
        ];
    }

    private function httpGet(string $url, array $headers = [], int $timeout = 8): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => 'gzip,deflate',
        ]);
        $output = curl_exec($ch);
        $error = curl_error($ch);
        if (PHP_VERSION_ID < 80500) { @curl_close($ch); }

        return (!$error && is_string($output)) ? $output : null;
    }

    private function successResponse(array $data): array
    {
        return [
            'code' => 200,
            'msg'  => '解析成功',
            'data' => $data
        ];
    }

    private function errorResponse(int $code, string $msg): array
    {
        return [
            'code' => $code,
            'msg'  => $msg,
            'data' => null
        ];
    }
}
