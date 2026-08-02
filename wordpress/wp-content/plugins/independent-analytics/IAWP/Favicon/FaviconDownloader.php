<?php

namespace IAWP\Favicon;

use IAWP\Favicon\Converter\AbstractConverter;
use IAWP\Utils\URL;
/** @internal */
class FaviconDownloader
{
    private \IAWP\Favicon\Favicon $favicon;
    public function __construct(\IAWP\Favicon\Favicon $favicon)
    {
        $this->favicon = $favicon;
    }
    public function download() : void
    {
        try {
            $this->attempt_download();
        } catch (\Throwable $e) {
            //
        }
    }
    private function attempt_download()
    {
        if (!$this->has_required_php_extensions()) {
            return;
        }
        if ($this->favicon->exists()) {
            return;
        }
        $url = new URL(\sanitize_url($this->favicon->domain));
        if ($url->is_valid_url() === \false || $url->get_domain() === null) {
            return;
        }
        $content = $this->fetch($url->get_url());
        if ($content === null) {
            return;
        }
        try {
            $favicon_url = $this->extractFaviconUrl($content, $url->get_url());
        } catch (\Throwable $e) {
            return;
        }
        if ($favicon_url === null) {
            return;
        }
        $path = \IAWPSCOPED\iawp_upload_path_to('iawp-favicons/' . $this->favicon->file_name());
        $blob = $this->fetch($favicon_url);
        if (!$blob) {
            return;
        }
        $converter = AbstractConverter::make($blob, $path);
        if (!$converter) {
            return;
        }
        $converter->save();
    }
    private function fetch(string $url) : ?string
    {
        $response = \wp_safe_remote_get($url, ["user-agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36", 'timeout' => 10, 'redirection' => 10]);
        if (\is_wp_error($response)) {
            return null;
        }
        return $response['body'];
    }
    private function extractFaviconUrl(string $html, string $baseUrl) : ?string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        // Suppress HTML parsing errors
        $xpath = new \DOMXPath($dom);
        // XPath Query: Find link tags in <head> with 'icon' in the 'rel' attribute
        $query = "//head/link[contains(translate(@rel, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'icon')]";
        $linkNodes = $xpath->query($query);
        if ($linkNodes->length > 0) {
            $bestIcon = null;
            foreach ($linkNodes as $node) {
                $rel = \strtolower($node->getAttribute('rel'));
                $href = $node->getAttribute('href');
                $sizes = $node->getAttribute('sizes');
                $score = 0;
                // Simple rel categorization
                if (\strpos($rel, 'shortcut') !== \false) {
                    $rel = 'shortcut icon';
                } elseif (\strpos($rel, 'apple') !== \false) {
                    $rel = 'apple-touch-icon';
                    if (!$sizes) {
                        $score = 180 * 180;
                    }
                } elseif (\strpos($rel, 'icon') !== \false) {
                    $rel = 'icon';
                } else {
                    continue;
                }
                if ($sizes) {
                    if (\strtolower($sizes) === 'any') {
                        $score = \PHP_INT_MAX;
                    } else {
                        $dimensions = \explode(' ', $sizes);
                        foreach ($dimensions as $dim) {
                            $parts = \explode('x', \strtolower($dim));
                            if (\count($parts) === 2) {
                                $w = (int) $parts[0];
                                $h = (int) $parts[1];
                                $area = $w * $h;
                                if ($area > $score) {
                                    $score = $area;
                                }
                            }
                        }
                    }
                }
                $candidate = ['href' => $href, 'rel' => $rel, 'score' => $score];
                if ($bestIcon === null) {
                    $bestIcon = $candidate;
                    continue;
                }
                // Prioritize by score (size)
                if ($candidate['score'] > $bestIcon['score']) {
                    $bestIcon = $candidate;
                } elseif ($candidate['score'] === $bestIcon['score']) {
                    // Tie-breaker: Priority based on rel type
                    $priorities = ['apple-touch-icon' => 3, 'icon' => 2, 'shortcut icon' => 1];
                    $currentP = $priorities[$candidate['rel']] ?? 0;
                    $bestP = $priorities[$bestIcon['rel']] ?? 0;
                    if ($currentP > $bestP) {
                        $bestIcon = $candidate;
                    }
                }
            }
            if ($bestIcon) {
                $relativeUrl = $bestIcon['href'];
                // Construct the absolute URL
                if (\strpos($relativeUrl, '//') === 0) {
                    return "https:{$relativeUrl}";
                } elseif (\strpos($relativeUrl, 'http') === 0) {
                    return $relativeUrl;
                } else {
                    return \rtrim($baseUrl, '/') . '/' . \ltrim($relativeUrl, '/');
                }
            }
        }
        // 4. Final Fallback: Check root /favicon.ico since DOM parsing failed
        // We use the fetch method to gain the benefits of browser-mimicking headers
        $standardIco = \rtrim($baseUrl, '/') . "/favicon.ico";
        $icoContent = $this->fetch($standardIco);
        // Ensure we got content and it's not HTML (which would indicate a challenge page or 404 page)
        if ($icoContent !== null && \strpos($icoContent, '<html') === \false && \strpos($icoContent, '<!DOCTYPE') === \false) {
            return $standardIco;
        }
        return null;
    }
    private function has_required_php_extensions() : bool
    {
        return \extension_loaded('gd') || \extension_loaded('imagick');
    }
    public static function for(\IAWP\Favicon\Favicon $favicon) : self
    {
        return new self($favicon);
    }
}
