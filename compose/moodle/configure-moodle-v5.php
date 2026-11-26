<?php
// CR465 Groupe C V5 - preparation API REST minimale pour l'inscription n8n.
// Appeler --token-only pour recuperer la valeur sans l'imprimer dans les logs cloud-init.
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
\core\session\manager::set_user(get_admin());

$tokenonly = in_array('--token-only', $argv ?? [], true);
$roleonly = in_array('--role-only', $argv ?? [], true);
if ($tokenonly || $roleonly) {
    if ($tokenonly) {
        $service = $DB->get_record('external_services', ['shortname' => 'n8n_moodle'], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => 'n8n-service', 'deleted' => 0], '*', MUST_EXIST);
        $token = $DB->get_record('external_tokens', [
            'userid' => $user->id, 'externalserviceid' => $service->id, 'tokentype' => 0
        ], '*', MUST_EXIST);
        echo $token->token;
    } else {
        $student = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        echo $student->id;
    }
    exit(0);
}

set_config('enablewebservices', 1);
set_config('webserviceprotocols', 'rest');

// Correspondance du catalogue du collegue: chaque SKU est un cours Moodle existant.
$courses = [
    'CYBER465' => 'Introduction à la cybersécurité',
    'DOCKER101' => 'Docker Essentials',
    'LINUX201' => 'Administration Linux',
    'DRUPAL301' => 'Drupal 11 Avancé',
    'K8S101' => 'Kubernetes Fundamentals',
    'N8N101' => 'Automatisation avec n8n',
    'PGSQL201' => 'PostgreSQL Administration',
    'GIT101' => 'Git et GitLab',
    'SECLNX301' => 'Sécurité Linux',
    'ANSIBLE101' => 'Ansible Débutant',
    'CLOUD101' => 'Cloud Init et Multipass',
];
$manual = enrol_get_plugin('manual');
if (!$manual) {
    throw new RuntimeException('Plugin enrolment manual absent');
}
foreach ($courses as $shortname => $fullname) {
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) {
        $data = (object)[
            'fullname' => $fullname,
            'shortname' => $shortname,
            'category' => 1,
            'format' => 'topics',
            'numsections' => 3,
            'visible' => 1,
            'summary' => 'Formation Groupe C accessible après achat simulé.',
            'summaryformat' => FORMAT_HTML,
        ];
        $course = create_course($data);
    }
    $instances = enrol_get_instances($course->id, false);
    $hasmanual = false;
    foreach ($instances as $instance) {
        if ($instance->enrol === 'manual') {
            $hasmanual = true;
            break;
        }
    }
    if (!$hasmanual) {
        $manual->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
    }
}

$serviceuser = $DB->get_record('user', ['username' => 'n8n-service', 'deleted' => 0]);
if (!$serviceuser) {
    $u = (object)[
        'username' => 'n8n-service',
        'firstname' => 'n8n',
        'lastname' => 'Service',
        'email' => 'n8n-service@groupec.test',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'password' => bin2hex(random_bytes(32)) . 'Aa1!',
    ];
    $id = user_create_user($u);
    $serviceuser = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
}
$system = context_system::instance();
$role = $DB->get_record('role', ['shortname' => 'n8n_api']);
if (!$role) {
    $roleid = create_role('n8n API Integration', 'n8n_api', 'Inscription aux cours du laboratoire');
} else {
    $roleid = $role->id;
}
set_role_contextlevels($roleid, [CONTEXT_SYSTEM, CONTEXT_COURSE]);
role_assign($roleid, $serviceuser->id, $system->id);
foreach (['webservice/rest:use', 'moodle/user:viewdetails', 'moodle/course:useremail',
          'moodle/course:view', 'enrol/manual:enrol', 'moodle/role:assign'] as $cap) {
    assign_capability($cap, CAP_ALLOW, $roleid, $system->id, true);
}

// V5.5: CORRECTIF VALIDE sur VM. Sans ce droit, l'API REST
// core_user_get_users_by_field(field=email) peut renvoyer [] meme si
// l'utilisateur OAuth2 existe dans Moodle. Role limite a n8n-service.
set_config('showuseridentity', 'email');
$identityrole = $DB->get_record('role', ['shortname' => 'groupec_n8n_identity']);
if (!$identityrole) {
    $identityroleid = create_role('Groupe C - Identite API n8n', 'groupec_n8n_identity',
        'Lecture des identites des utilisateurs pour la recherche REST n8n');
} else {
    $identityroleid = (int) $identityrole->id;
}
set_role_contextlevels($identityroleid, [CONTEXT_SYSTEM]);
assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $identityroleid, $system->id, true);
role_assign($identityroleid, $serviceuser->id, $system->id);
reload_all_capabilities();
if (!has_capability('moodle/site:viewuseridentity', $system, $serviceuser->id)) {
    throw new RuntimeException('Le compte API n8n ne peut pas rechercher les utilisateurs par courriel');
}

$student = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
if (!$DB->record_exists('role_allow_assign', ['roleid' => $roleid, 'allowassign' => $student->id])) {
    $DB->insert_record('role_allow_assign', ['roleid' => $roleid, 'allowassign' => $student->id]);
}

$service = $DB->get_record('external_services', ['shortname' => 'n8n_moodle']);
if (!$service) {
    $s = (object)[
        'name' => 'n8n Moodle Integration', 'shortname' => 'n8n_moodle',
        'enabled' => 1, 'restrictedusers' => 1, 'downloadfiles' => 0,
        'uploadfiles' => 0, 'timecreated' => time(),
    ];
    $s->id = $DB->insert_record('external_services', $s);
    $service = $s;
}
// Aucun droit de creation de cours via API: les 11 cours pre-crees sont la liste autorisee.
foreach (['core_user_get_users_by_field', 'core_course_get_courses_by_field',
          'enrol_manual_enrol_users'] as $func) {
    if (!$DB->record_exists('external_services_functions', [
        'externalserviceid' => $service->id, 'functionname' => $func])) {
        $DB->insert_record('external_services_functions', [
            'externalserviceid' => $service->id, 'functionname' => $func]);
    }
}
if (!$DB->record_exists('external_services_users', [
    'externalserviceid' => $service->id, 'userid' => $serviceuser->id])) {
    $DB->insert_record('external_services_users', [
        'externalserviceid' => $service->id,
        'userid' => $serviceuser->id,
        'timecreated' => time(),
    ]);
}
if (!$DB->record_exists('external_tokens', [
    'userid' => $serviceuser->id, 'externalserviceid' => $service->id, 'tokentype' => 0])) {
    $DB->insert_record('external_tokens', (object)[
        'token' => bin2hex(random_bytes(32)),
        'tokentype' => 0,
        'userid' => $serviceuser->id,
        'externalserviceid' => $service->id,
        'contextid' => $system->id,
        'creatorid' => get_admin()->id,
        'timecreated' => time(),
        'validuntil' => 0,
        'iprestriction' => '',
    ]);
}
// V5.4: en cas de redeploiement, corriger aussi un etudiant SSO deja cree.
// La premiere connexion sur une VM neuve est geree par local_groupec_ssofix.
$demo = $DB->get_record('user', ['email' => 'etudiant-test@groupec.test', 'deleted' => 0]);
if ($demo && $demo->auth === 'oauth2') {
    unset_user_preference('auth_forcepasswordchange', $demo->id);
}
echo 'SUCCES V5.4: catalogue de 11 cours, API REST et compte de service n8n.' . PHP_EOL;
