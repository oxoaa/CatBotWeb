<?php
/**
 * ==============================================================================
 * 抖音短视频 / 图集去水印解析 API (PHP 高可用版)
 * ==============================================================================
 * 
 * 功能特性:
 * 1. 自动提取: 支持混合文字、表情包的抖音分享文本，自动提取短链接；
 * 2. 自动重定向: 支持 v.douyin.com 短链跟踪重定向并提取核心 Item ID；
 * 3. 完整支持: 完美支持单视频解析、多图图集无水印下载、背景音乐提取；
 * 4. 原画直链: 自动提取无水印原画 1080P/高清 CDN 直链；
 * 5. 容灾兜底: 自带主备双通道策略，遭遇机房 IP 风控时自动无缝兜底，稳定可用。
 * 
 * 调用方式:
 *   - GET 请求:  https://your-domain.com/douyin.php?url=https://v.douyin.com/xxxx/
 *   - POST 请求: https://your-domain.com/douyin.php (body: url=https://v.douyin.com/xxxx/)
 *   - JSON 请求: https://your-domain.com/douyin.php (body: {"url": "https://v.douyin.com/xxxx/"})
 * 
 * 返回格式: 标准 JSON
 * ==============================================================================
 */

declare(strict_types=1);

// 屏蔽 Deprecated 等不影响运行的警告，保证 JSON 输出纯净
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// 跨域支持与响应头设置
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

// 处理 OPTIONS 预检请求
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 1. 获取输入参数
$input = getRequestUrl();

if (empty($input)) {
    echo json_encode([
        'code' => 400,
        'msg'  => '缺少必要参数: 请传入抖音分享链接 (GET/POST url 参数或 JSON)',
        'data' => null
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// 2. 执行解析
$parser = new DouyinWatermarkParser();
$response = $parser->parse($input);

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;

// ==============================================================================
// 核心解析类实现
// ==============================================================================

class DouyinWatermarkParser
{
    private string $mobileUA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1';
    private string $desktopUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    /**
     * 解析主函数
     */
    public function parse(string $text): array
    {
        // 步骤 1: 从文本中提取链接
        $shareUrl = $this->extractUrlFromText($text);
        if (!$shareUrl) {
            return $this->errorResponse(400, '未能从输入文本中识别出有效的 URL 链接');
        }

        // 步骤 2: 跟踪重定向获取最终长链接
        $finalUrl = $this->resolveFinalUrl($shareUrl);
        if (!$finalUrl) {
            $finalUrl = $shareUrl;
        }

        // 步骤 3: 提取视频/作品 ID (item_id)
        $itemId = $this->extractItemId($finalUrl);

        // 步骤 4: 执行解析策略
        // 策略 A: 官方 SSR 数据主通道解析 (免 Cookie 直提)
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

        // 策略 C: 智能备用容灾通道 (防境外或机房 IP 被官方 WAF 拦截)
        $dataC = $this->parseByFallbackGateway($shareUrl);
        if ($dataC) {
            return $this->successResponse($dataC);
        }

        return $this->errorResponse(500, '解析失败: 视频可能已被作者删除、设为私密，或当前机房 IP 触发了官方防爬拦截');
    }

    /**
     * 从任意混合文字中提取 URL
     */
    private function extractUrlFromText(string $text): ?string
    {
        if (preg_match('/https?:\/\/[a-zA-Z0-9_\-\.\/\?=&%#]+/i', $text, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * 跟踪 301/302 重定向获取最终目标 URL
     */
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

    /**
     * 提取作品 Item ID
     */
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

    /**
     * 策略 A: 官方分享页 SSR 数据解析
     */
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

        // 提取 window._ROUTER_DATA
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

        // 提取 <script id="RENDER_DATA">
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

    /**
     * 策略 B: 官方移动 API 接口解析
     */
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

    /**
     * 策略 C: 智能备用容灾通道
     */
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

            // 提取视频或图集地址
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

    /**
     * 格式化官方视频/图集数据为统一标准结构
     */
    private function formatDouyinItem(array $item, string $itemId): array
    {
        $desc = $item['desc'] ?? '';
        $author = [
            'nickname' => $item['author']['nickname'] ?? '抖音用户',
            'uid'      => (string)($item['author']['unique_id'] ?? $item['author']['uid'] ?? ''),
            'avatar'   => $item['author']['avatar_thumb']['url_list'][0] ?? ($item['author']['avatar_medium']['url_list'][0] ?? ''),
        ];

        // 封面提取 (优先原图封面)
        $cover = $item['video']['origin_cover']['url_list'][0] 
              ?? $item['video']['cover']['url_list'][0] 
              ?? ($item['video']['dynamic_cover']['url_list'][0] ?? '');

        // 音乐提取
        $music = [
            'title'  => $item['music']['title'] ?? '原声音乐',
            'author' => $item['music']['author'] ?? ($author['nickname'] ?? ''),
            'url'    => $item['music']['play_url']['url_list'][0] ?? null,
        ];

        // 判断是否为图集
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
            // 图集模式
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

        // 视频模式：提取无水印直链
        // 核心去水印逻辑:
        // 1. playwm (play with watermark) 替换为 play
        // 2. 或提取 vid 拼接原画直链播放端点
        $rawPlayUrl = $item['video']['play_addr']['url_list'][0] ?? null;
        $videoUrl = null;

        if ($rawPlayUrl) {
            $videoUrl = str_replace('playwm', 'play', $rawPlayUrl);
        }

        $vid = $item['video']['play_addr']['uri'] ?? null;
        if ($vid) {
            // 原画 302 播放直链
            $originalEndpoint = "https://aweme.snssdk.com/aweme/v1/play/?video_id=" . rawurlencode((string)$vid) . "&ratio=default&line=0";
            // 跟随一次 302 尝试获取真实 CDN 直链
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

    /**
     * 发送 HTTP GET 请求
     */
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

    /**
     * 成功响应封装
     */
    private function successResponse(array $data): array
    {
        return [
            'code' => 200,
            'msg'  => '解析成功',
            'data' => $data
        ];
    }

    /**
     * 错误响应封装
     */
    private function errorResponse(int $code, string $msg): array
    {
        return [
            'code' => $code,
            'msg'  => $msg,
            'data' => null
        ];
    }
}

// ==============================================================================
// 辅助函数
// ==============================================================================

/**
 * 提取输入参数中的 URL (支持 GET/POST/JSON)
 */
function getRequestUrl(): ?string
{
    // 1. GET 参数
    if (!empty($_GET['url'])) {
        return trim((string)$_GET['url']);
    }

    // 2. POST 表单参数
    if (!empty($_POST['url'])) {
        return trim((string)$_POST['url']);
    }

    // 3. POST JSON 数据
    $body = file_get_contents('php://input');
    if (!empty($body)) {
        $json = json_decode($body, true);
        if (!empty($json['url'])) {
            return trim((string)$json['url']);
        }
        // 如果整段纯文本直接是链接
        if (str_starts_with(trim($body), 'http')) {
            return trim($body);
        }
    }

    // 4. Query String 全路径直接截取 (兼容部分特殊编码 url= 参数)
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (($pos = strpos($uri, 'url=')) !== false) {
        $raw = substr($uri, $pos + 4);
        return trim(urldecode($raw));
    }

    return null;
}
