<?php
// CR465. Laisser accessibles a Moodle uniquement Traefik OIDC sur le
// reseau applicatif, sans desactiver le blocage des autres IP privees.
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';

$targetip = $argv[1] ?? '';
if (!filter_var($targetip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    fwrite(STDERR, "ERREUR: IP du proxy Keycloak invalide.\n");
    exit(1);
}
$dns = gethostbynamel('keycloak.groupec.test');
if (!$dns || count($dns) !== 1 || $dns[0] !== $targetip) {
    fwrite(STDERR, "ERREUR: resolution DNS Keycloak differente de l'IP Traefik attendue.\n");
    exit(1);
}

/** Retire une seule IP d'un CIDR IPv4, en conservant le blocage des autres. */
function exclure_ip_cidr(string $cidr, string $ip): array {
    $pieces = explode('/', $cidr, 2);
    if (count($pieces) !== 2 || !filter_var($pieces[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        || !ctype_digit($pieces[1]) || (int)$pieces[1] > 32) {
        return [$cidr];
    }
    $prefix = (int)$pieces[1];
    $shift = 32 - $prefix;
    $mask = $prefix === 0 ? 0 : ((0xffffffff << $shift) & 0xffffffff);
    $start = ip2long($pieces[0]) & $mask;
    $target = ip2long($ip);
    if (($target & $mask) !== $start) {
        return [$cidr];
    }
    $result = [];
    $current = $start;
    for ($newprefix = $prefix + 1; $newprefix <= 32; $newprefix++) {
        $halfsize = 1 << (32 - $newprefix);
        if ($target < $current + $halfsize) {
            $result[] = long2ip($current + $halfsize) . '/' . $newprefix;
        } else {
            $result[] = long2ip($current) . '/' . $newprefix;
            $current += $halfsize;
        }
    }
    return $result;
}

$ports = preg_split('/[\s,;]+/', (string)($CFG->curlsecurityallowedport ?? '80 443'), -1, PREG_SPLIT_NO_EMPTY);
$ports = array_values(array_unique(array_merge($ports, ['80', '443', '4443'])));
set_config('curlsecurityallowedport', implode("\n", $ports));
$CFG->curlsecurityallowedport = implode("\n", $ports);

$blocked = preg_split('/[\s,;]+/', (string)($CFG->curlsecurityblockedhosts ?? ''), -1, PREG_SPLIT_NO_EMPTY);
$filtered = [];
foreach ($blocked as $entry) {
    if ($entry === $targetip || $entry === $targetip . '/32') {
        continue;
    }
    foreach (exclure_ip_cidr($entry, $targetip) as $net) {
        $filtered[] = $net;
    }
}
$filtered = array_values(array_unique($filtered));
set_config('curlsecurityblockedhosts', implode("\n", $filtered));
$CFG->curlsecurityblockedhosts = implode("\n", $filtered);

$helper = new \core\files\curl_security_helper();
$url = 'https://keycloak.groupec.test:4443/realms/groupec/.well-known/openid-configuration';
if ($helper->url_is_blocked($url)) {
    fwrite(STDERR, "ERREUR: OIDC encore bloque par curl_security_helper.\n");
    exit(1);
}
echo "SUCCES: cURL Moodle autorise exclusivement l'IP Traefik pour le SSO sur port 4443.\n";
