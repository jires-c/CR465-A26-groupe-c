<?php
// CR465 - SSO Moodle -> Keycloak, lors du premier boot cloud-init.
// Script idempotent : ne duplique ni fournisseur ni endpoints.
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
\core\session\manager::set_user(get_admin());

$secret = trim(stream_get_contents(STDIN));
if (!preg_match('/^[a-f0-9]{48,128}$/', $secret)) {
    fwrite(STDERR, "ERREUR : secret client OIDC invalide.\n");
    exit(1);
}
$issuername = 'Keycloak Groupe C';
$baseurl = 'https://keycloak.groupec.test:4443/realms/groupec';

// Conserver les authentifications deja actives (dont email).
$auth = array_filter(array_map('trim', explode(',', (string)($CFG->auth ?? ''))));
if (!in_array('oauth2', $auth, true)) {
    $auth[] = 'oauth2';
    set_config('auth', implode(',', $auth));
}

$existing = $DB->get_record('oauth2_issuer', ['name' => $issuername]);
if ($existing) {
    $issuer = new \core\oauth2\issuer((int)$existing->id);
    $issuer->set('clientid', 'moodle');
    $issuer->set('clientsecret', $secret);
    $issuer->set('baseurl', $baseurl);
    $issuer->set('enabled', true);
    $issuer->set('showonloginpage', \core\oauth2\issuer::LOGINONLY);
    $issuer->set('loginscopes', 'openid profile email');
    $issuer->update();
    echo "Fournisseur Keycloak deja present, parametres verifies.\n";
} else {
    // La creation peut enregistrer l'issuer puis echouer pendant la
    // decouverte reseau. Reprendre cet issuer et creer les endpoints connus.
    try {
        $issuer = \core\oauth2\api::create_issuer((object)[
        'name' => $issuername,
        // Champ image NOT NULL dans Moodle: valeur vide autorisee.
        'image' => '',
        'clientid' => 'moodle',
        'clientsecret' => $secret,
        'baseurl' => $baseurl,
        'servicetype' => 'custom',
        'enabled' => true,
        'showonloginpage' => \core\oauth2\issuer::LOGINONLY,
        'loginscopes' => 'openid profile email',
        'requireconfirmation' => false,
        ]);
    } catch (\Throwable $err) {
        $partial = $DB->get_record('oauth2_issuer', ['name' => $issuername]);
        if (!$partial) {
            throw $err;
        }
        $issuer = new \core\oauth2\issuer((int)$partial->id);
        $issuer->set('clientid', 'moodle');
        $issuer->set('clientsecret', $secret);
        $issuer->set('baseurl', $baseurl);
        $issuer->set('enabled', true);
        $issuer->set('showonloginpage', \core\oauth2\issuer::LOGINONLY);
        $issuer->set('loginscopes', 'openid profile email');
        $issuer->update();
        echo "Issuer partiellement cree lors de la decouverte; endpoints configures explicitement.\n";
    }
    echo "Fournisseur Keycloak cree ou repare.\n";
}

$id = (int)$issuer->get('id');
$endpointurls = [
    'authorization_endpoint' => $baseurl . '/protocol/openid-connect/auth',
    'token_endpoint' => $baseurl . '/protocol/openid-connect/token',
    'userinfo_endpoint' => $baseurl . '/protocol/openid-connect/userinfo',
];
foreach ($endpointurls as $name => $url) {
    $row = $DB->get_record('oauth2_endpoint', ['issuerid' => $id, 'name' => $name]);
    if ($row) {
        if ($row->url !== $url) {
            $endpoint = new \core\oauth2\endpoint((int)$row->id);
            $endpoint->set('url', $url);
            $endpoint->update();
        }
    } else {
        \core\oauth2\api::create_endpoint((object)[
            'issuerid' => $id, 'name' => $name, 'url' => $url,
        ]);
    }
}

// Keycloak userinfo : given_name/family_name/email -> champs Moodle obligatoires.
foreach (['given_name' => 'firstname', 'family_name' => 'lastname', 'email' => 'email'] as $external => $internal) {
    if (!$DB->record_exists('oauth2_user_field_mapping', ['issuerid' => $id, 'internalfield' => $internal])) {
        \core\oauth2\api::create_user_field_mapping((object)[
            'issuerid' => $id, 'externalfield' => $external, 'internalfield' => $internal,
        ]);
    }
}

if (!$issuer->is_available_for_login()) {
    fwrite(STDERR, "ERREUR : fournisseur non utilisable sur la page de connexion.\n");
    exit(1);
}
foreach (array_keys($endpointurls) as $name) {
    if (!$DB->record_exists('oauth2_endpoint', ['issuerid' => $id, 'name' => $name])) {
        fwrite(STDERR, "ERREUR : endpoint OIDC manquant: $name\n");
        exit(1);
    }
}
$mappings = $DB->count_records('oauth2_user_field_mapping', ['issuerid' => $id]);
if ($mappings < 3) {
    fwrite(STDERR, "ERREUR : mappings SSO incomplets ($mappings/3).\n");
    exit(1);
}
echo "SUCCES : fournisseur Keycloak OAuth2 configure dans Moodle (issuer id=$id, endpoints et mappings presents).\n";
