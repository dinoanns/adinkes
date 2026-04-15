<?php
define('AES_KEY', 'adinkes_hearts360_key');
define('AES_IV',  substr(hash('sha256', AES_KEY), 0, 16));

function paramEncrypt($string) {
    $encrypted = openssl_encrypt($string, 'AES-128-CBC', AES_KEY, 0, AES_IV);
    return urlencode(base64_encode($encrypted));
}

function paramDecrypt($string) {
    $decoded = base64_decode(urldecode($string));
    return openssl_decrypt($decoded, 'AES-128-CBC', AES_KEY, 0, AES_IV);
}

function decode($uri) {
    $query = parse_url($uri, PHP_URL_QUERY);
    if (!$query) return [];
    $decrypted = paramDecrypt($query);
    if (!$decrypted) return [];
    parse_str($decrypted, $params);
    return $params;
}
