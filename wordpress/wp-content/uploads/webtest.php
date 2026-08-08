<?php
require '/var/www/html/wp-load.php';
$data = get_post_meta(2660, '_elementor_data', true);
header('Content-Type: text/plain');
echo "len=" . strlen($data) . "\n";
echo "new coord: " . (strpos($data, '33.86421442298441') !== false ? 'YES' : 'NO') . "\n";
echo "old coord: " . (strpos($data, '33.8681') !== false ? 'YES' : 'NO') . "\n";
