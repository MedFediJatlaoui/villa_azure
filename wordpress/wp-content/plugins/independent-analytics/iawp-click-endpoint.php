<?php

// Warning. This file is a standalone PHP file whose sole purpose is to sanitize input and store
// that input in a CSV for later processing. WordPress is not loaded and the classes from the
// plugin are not available.

class Cache
{
    private array $attributes;

    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public function is_pro(): bool
    {
        return $this->attributes['is_pro'] === true;
    }

    public function avoid_temporary_directory(): bool
    {
        return $this->attributes['avoid_temporary_directory'] === true;
    }

    public function get_custom_ip_header(): ?string
    {
        if (array_key_exists('custom_ip_header', $this->attributes)) {
            return trim($this->attributes['custom_ip_header']);
        }

        return null;
    }

    public function get_visitor_token_salt(): string
    {
        return trim($this->attributes['visitor_token_salt']);
    }
}

function get_cache(): ?Cache
{
    $suffix = file_suffix();
    $file   = __DIR__ . "/iawp-click-config{$suffix}.php";

    if (!is_readable($file)) {
        return null;
    }

    $contents = file_get_contents($file);

    if (false === $contents) {
        return null;
    }

    $lines = explode("\n", $contents);

    if (count($lines) !== 2) {
        return null;
    }

    $json = trim($lines[1]);
    $data = json_decode($json, true);

    if (is_null($data)) {
        return null;
    }

    return new Cache($data);

}

function get_ip_address(Cache $cache): ?string
{
    $headers = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
        'HTTP_CF_CONNECTING_IP',
        'HTTP_CLIENT_IP',
        'HTTP_INCAP_CLIENT_IP',
        'HTTP_CF_CONNECTING_IP',
    ];

    if (is_string($cache->get_custom_ip_header())) {
        array_unshift($headers, $cache->get_custom_ip_header());
    }

    foreach ($headers as $header) {
        if (isset($_SERVER[$header])) {
            return explode(',', $_SERVER[$header])[0];
        }
    }

    return null;
}

function get_visitor_token(Cache $cache): ?string
{
    return md5($cache->get_visitor_token_salt() . get_ip_address($cache) . $_SERVER['HTTP_USER_AGENT']);
}

function trailing_slash(string $path): string
{
    $path = rtrim($path, '/\\');

    return $path . DIRECTORY_SEPARATOR;
}

/**
 * A site will have a non-empty click file suffix only if it's part of a multi-site network.
 */
function file_suffix(): string
{
    $site_id = get_body()['siteId'] ?? null;

    if (is_string($site_id) && strlen($site_id) === 8) {
        return '-' . $site_id;
    }

    return '';
}

function get_click_data_file(Cache $cache): string
{
    $suffix = file_suffix();

    if (!$cache->avoid_temporary_directory()) {
        $text_file = trailing_slash(sys_get_temp_dir()) . "iawp-click-data{$suffix}.txt";

        if (is_file($text_file) && is_readable($text_file) && is_writable($text_file)) {
            return $text_file;
        }

        if (file_put_contents($text_file, "") !== false && file_get_contents($text_file) !== false) {
            return $text_file;
        }
    }

    $php_file = trailing_slash(__DIR__) . "iawp-click-data{$suffix}.php";

    if (is_file($php_file)) {
        return $php_file;
    }

    if (file_put_contents($php_file, "<?php exit; ?>\n") !== false) {
        return $php_file;
    }

    // Unable to create either data file so there's nowhere to store the click data
    exit;
}

function get_body()
{
    $json_body = file_get_contents('php://input');

    return json_decode($json_body, true);
}

$body = get_body();

if (is_null($body)) {
    exit;
}

$cache = get_cache();

if (is_null($cache) || !$cache->is_pro() || is_null(get_ip_address($cache))) {
    exit;
}

$click_data_file = get_click_data_file($cache);

$data = [
    'href'          => $body['href'],
    'classes'       => $body['classes'],
    'ids'           => $body['ids'],
    'payload'       => json_encode($body['payload']),
    'signature'     => $body['signature'],
    'visitor_token' => get_visitor_token($cache),
    'created_at'    => time(),
];

if (!is_readable($click_data_file) || !is_writable($click_data_file)) {
    exit;
}

$file_resource = fopen($click_data_file, 'a');

if (false === $file_resource) {
    exit;
}

fwrite($file_resource, json_encode($data) . "\n");
fclose($file_resource);
