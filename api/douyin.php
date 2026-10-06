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
        $itemId = $this->extractItemId($finalUrl) ?: $this->extractItemId($shareUrl);

        // 4. 优先策略: 智能高速免流通道 (自动解析视频与高清多图图集，防机房 IP 触发滑块/WAF)
        $dataGateway = $this->parseByFallbackGateway($shareUrl, $finalUrl, $itemId);
        if ($dataGateway) {
            return $this->successResponse($dataGateway);
        }

        // 5. 策略 B: 官方 SSR 数据主通道解析 (自适应视频与图集)
        if ($itemId) {
            $dataOfficial = $this->parseByOfficialPage($itemId, $finalUrl);
            if ($dataOfficial) {
                return $this->successResponse($dataOfficial);
            }
        }

        // 6. 策略 C: 官方移动 API 接口通道
        if ($itemId) {
            $dataApi = $this->parseByOfficialApi($itemId);
            if ($dataApi) {
                return $this->successResponse($dataApi);
            }
        }

        return $this->errorResponse(500, '解析失败: 该作品可能已被作者删除、设为私密，或当前机房 IP 触发了官方防爬拦截');
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
            '/\/note\/(\d+)/',
            '/\/slides\/(\d+)/',
            '/\/video\/(\d+)/',
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
     * 高速免流容灾网关解析 (完美兼容单视频、多图图集无水印下载)
     */
    private function parseByFallbackGateway(string $shareUrl, string $finalUrl = '', ?string $itemId = null): ?array
    {
        $id = (string)($itemId ?: $this->extractItemId($finalUrl) ?: $this->extractItemId($shareUrl) ?: '');
        
        $urlsToTry = array_unique(array_filter([$shareUrl, $finalUrl]));
        
        $gateways = [
            'https://api.s01s.cn/API/sp_jx/?url=',
        ];

        foreach ($urlsToTry as $targetUrl) {
            foreach ($gateways as $base) {
                $apiUrl = $base . urlencode($targetUrl);
                $res = $this->httpGet($apiUrl, ['User-Agent: ' . $this->desktopUA], 10);
                if (empty($res)) continue;

                $json = json_decode($res, true);
                if (empty($json) || !is_array($json)) continue;

                $data = $json['data'] ?? $json;

                // 1. 提取图集图片列表 (兼容 pics / images / img_list / photos / url 等各类命名)
                $images = [];
                $rawImages = $data['images'] 
                          ?? $data['pics'] 
                          ?? $data['img_list'] 
                          ?? $data['photos']
                          ?? ($json['images'] ?? ($json['pics'] ?? []));

                if (is_string($rawImages) && str_contains($rawImages, 'http')) {
                    $images = preg_split('/[,\r\n]+/', trim($rawImages));
                } elseif (is_array($rawImages)) {
                    foreach ($rawImages as $img) {
                        if (is_string($img) && str_starts_with(trim($img), 'http')) {
                            $images[] = trim($img);
                        } elseif (is_array($img)) {
                            $u = $img['url'] ?? $img['src'] ?? ($img['url_list'][0] ?? null);
                            if ($u && is_string($u) && str_starts_with(trim($u), 'http')) {
                                $images[] = trim($u);
                            }
                        }
                    }
                }

                // 若 url 字段本身是图片数组
                if (empty($images)) {
                    $rawUrl = $data['url'] ?? ($json['url'] ?? null);
                    if (is_array($rawUrl)) {
                        foreach ($rawUrl as $u) {
                            if (is_string($u) && str_starts_with(trim($u), 'http')) {
                                $images[] = trim($u);
                            }
                        }
                    }
                }

                // 2. 提取视频直链 (仅在非图集时)
                $video = null;
                if (empty($images)) {
                    $rawVideo = $data['video'] ?? $data['url'] ?? $data['video_url'] ?? ($data['play_url'] ?? ($json['video'] ?? ($json['url'] ?? '')));
                    if (is_string($rawVideo) && str_starts_with(trim($rawVideo), 'http')) {
                        $video = trim($rawVideo);
                    }
                }

                $title = $data['title'] ?? $data['desc'] ?? ($json['title'] ?? ($json['desc'] ?? ''));
                $cover = $data['cover'] ?? ($json['cover'] ?? ($images[0] ?? ''));

                if (!empty($video) || !empty($images)) {
                    $cleanImages = array_values(array_unique(array_filter($images)));
                    return [
                        'type'       => !empty($cleanImages) ? 'image' : 'video',
                        'item_id'    => $id,
                        'title'      => (string)$title,
                        'author'     => [
                            'nickname' => (string)($data['author'] ?? $json['author'] ?? '抖音创作者'),
                            'uid'      => (string)($data['uid'] ?? $json['uid'] ?? ''),
                            'avatar'   => (string)($data['avatar'] ?? $json['avatar'] ?? ''),
                        ],
                        'cover'      => (string)$cover,
                        'video_url'  => !empty($cleanImages) ? null : $video,
                        'music'      => [
                            'title'  => (string)($data['music']['title'] ?? '原声音乐'),
                            'author' => (string)($data['music']['author'] ?? ''),
                            'url'    => $data['music']['url'] ?? null,
                        ],
                        'images'     => $cleanImages
                    ];
                }
            }
        }

        return null;
    }

    /**
     * 官方 SSR 数据主通道解析 (自适应视频与图集)
     */
    private function parseByOfficialPage(string $itemId, string $finalUrl): ?array
    {
        $candidates = [];
        if (!empty($finalUrl) && str_contains($finalUrl, 'douyin.com')) {
            $candidates[] = $finalUrl;
        }
        $candidates[] = "https://www.iesdouyin.com/share/slides/{$itemId}/";
        $candidates[] = "https://www.iesdouyin.com/share/note/{$itemId}/";
        $candidates[] = "https://www.iesdouyin.com/share/video/{$itemId}/";
        $candidates[] = "https://www.douyin.com/note/{$itemId}";
        $candidates[] = "https://www.douyin.com/video/{$itemId}";

        foreach ($candidates as $url) {
            $html = $this->httpGet($url, [
                'User-Agent: ' . $this->mobileUA,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: zh-CN,zh;q=0.9',
                'Referer: https://www.douyin.com/'
            ], 4);

            if (empty($html)) continue;

            if (str_contains($html, '_$jsvmprt') || str_contains($html, 'security-check')) {
                break;
            }

            $item = null;

            if (preg_match('/window\._ROUTER_DATA\s*=\s*(.*?);?\s*<\/script>/s', $html, $m)) {
                $json = json_decode(trim($m[1]), true);
                if (!empty($json['loaderData']) && is_array($json['loaderData'])) {
                    foreach ($json['loaderData'] as $k => $v) {
                        if (!is_array($v)) continue;
                        if (!empty($v['videoInfoRes']['item_list'][0])) {
                            $item = $v['videoInfoRes']['item_list'][0];
                            break;
                        }
                        if (!empty($v['noteInfoRes']['item_list'][0])) {
                            $item = $v['noteInfoRes']['item_list'][0];
                            break;
                        }
                        if (!empty($v['item_list'][0])) {
                            $item = $v['item_list'][0];
                            break;
                        }
                        if (!empty($v['noteDetail'])) {
                            $item = $v['noteDetail'];
                            break;
                        }
                        if (!empty($v['videoDetail'])) {
                            $item = $v['videoDetail'];
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
                } elseif (!empty($json['app']['noteDetail'])) {
                    $item = $json['app']['noteDetail'];
                } elseif (!empty($json['app']['awemeDetail'])) {
                    $item = $json['app']['awemeDetail'];
                }
            }

            if (!$item) {
                $domImages = $this->extractImagesFromHtml($html);
                if (!empty($domImages)) {
                    $title = '';
                    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $tm)) {
                        $title = trim(str_replace(['- 抖音', '_抖音', '抖音'], '', $tm[1]));
                    }
                    return [
                        'type'       => 'image',
                        'item_id'    => $itemId,
                        'title'      => $title,
                        'author'     => [
                            'nickname' => '抖音创作者',
                            'uid'      => '',
                            'avatar'   => '',
                        ],
                        'cover'      => $domImages[0],
                        'video_url'  => null,
                        'music'      => [
                            'title'  => '原声音乐',
                            'author' => '',
                            'url'    => null,
                        ],
                        'images'     => $domImages
                    ];
                }
            }

            if ($item) {
                return $this->formatDouyinItem($item, $itemId, $html);
            }
        }

        return null;
    }

    private function parseByOfficialApi(string $itemId): ?array
    {
        $url = "https://www.iesdouyin.com/web/api/v2/aweme/iteminfo/?item_ids={$itemId}";
        $res = $this->httpGet($url, [
            'User-Agent: ' . $this->mobileUA,
            'Referer: https://www.iesdouyin.com/'
        ], 3);

        if (empty($res)) return null;
        $json = json_decode($res, true);
        if (empty($json['item_list'][0])) return null;

        return $this->formatDouyinItem($json['item_list'][0], $itemId);
    }

    private function extractImagesFromHtml(string $html): array
    {
        $images = [];
        if (preg_match_all('/https?:\/\/[a-zA-Z0-9\.\_\-]*douyinpic\.com\/tos-cn-i-0813\/[a-zA-Z0-9\_\-]+/i', $html, $matches)) {
            $seen = [];
            foreach ($matches[0] as $url) {
                $url = preg_replace('/^http:/i', 'https:', $url);
                $base = explode('?', explode('~', $url)[0])[0];
                if (!isset($seen[$base])) {
                    $seen[$base] = true;
                    $images[] = $base;
                }
            }
        }
        return $images;
    }

    private function formatDouyinItem(array $item, string $itemId, string $html = ''): array
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
                if (is_string($img)) {
                    $imagesList[] = $img;
                } elseif (is_array($img)) {
                    $imgUrl = $img['download_url_list'][0] 
                           ?? $img['origin_image']['url_list'][0]
                           ?? $img['display_image']['url_list'][0]
                           ?? ($img['url_list'][0] ?? ($img['urlList'][0] ?? null));
                    if ($imgUrl) $imagesList[] = $imgUrl;
                }
            }
        }

        if (empty($imagesList) && !empty($item['image_post_info']['images']) && is_array($item['image_post_info']['images'])) {
            foreach ($item['image_post_info']['images'] as $img) {
                if (is_string($img)) {
                    $imagesList[] = $img;
                } elseif (is_array($img)) {
                    $imgUrl = $img['display_image']['url_list'][0] 
                           ?? $img['origin_image']['url_list'][0]
                           ?? ($img['url_list'][0] ?? ($img['urlList'][0] ?? null));
                    if ($imgUrl) $imagesList[] = $imgUrl;
                }
            }
        }

        if (empty($imagesList) && !empty($html)) {
            $imagesList = $this->extractImagesFromHtml($html);
        }

        $imagesList = array_values(array_unique($imagesList));

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

// ==============================================================================
// 辅助函数
// ==============================================================================

/**
 * 提取输入参数中的 URL (支持 GET/POST/JSON)
 */
function getRequestUrl(): ?string
{
    if (!empty($_GET['url'])) {
        return trim((string)$_GET['url']);
    }

    if (!empty($_POST['url'])) {
        return trim((string)$_POST['url']);
    }

    $body = file_get_contents('php://input');
    if (!empty($body)) {
        $json = json_decode($body, true);
        if (!empty($json['url'])) {
            return trim((string)$json['url']);
        }
        if (str_starts_with(trim($body), 'http')) {
            return trim($body);
        }
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (($pos = strpos($uri, 'url=')) !== false) {
        $raw = substr($uri, $pos + 4);
        return trim(urldecode($raw));
    }

    return null;
}
