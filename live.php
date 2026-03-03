<?php
require 'config.php';
require 'localize.php';
require 'vnstat.php';

validate_input();

function read_counter($path)
{
    if (!is_readable($path)) {
        return null;
    }

    $value = trim(file_get_contents($path));
    if ($value === '' || !ctype_digit($value)) {
        return null;
    }

    return $value;
}

$rx_path = "/sys/class/net/$iface/statistics/rx_bytes";
$tx_path = "/sys/class/net/$iface/statistics/tx_bytes";

$rx_bytes = read_counter($rx_path);
$tx_bytes = read_counter($tx_path);

header('Content-type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($rx_bytes === null || $tx_bytes === null) {
    http_response_code(500);
    print json_encode(array(
        'error' => 'unable to read interface counters',
    ));
    exit;
}

print json_encode(array(
    'interface' => $iface,
    'rx_bytes' => $rx_bytes,
    'tx_bytes' => $tx_bytes,
    'timestamp_ms' => round(microtime(true) * 1000),
));
