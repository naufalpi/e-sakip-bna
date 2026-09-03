<?php

$configuredProxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted reverse proxies
    |--------------------------------------------------------------------------
    |
    | Isi dengan IP/CIDR proxy yang meneruskan request ke aplikasi, dipisahkan
    | koma. Jangan gunakan "*" kecuali aplikasi hanya dapat diakses melalui
    | jaringan proxy tepercaya.
    |
    */
    'proxies' => match (true) {
        $configuredProxies === '' => null,
        in_array($configuredProxies, ['*', '**'], true) => $configuredProxies,
        default => array_values(array_filter(array_map('trim', explode(',', $configuredProxies)))),
    },
];
