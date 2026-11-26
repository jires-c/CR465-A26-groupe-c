<?php
// Ne jamais assouplir les controles d'authentification des autres utilisateurs.
namespace local_groupec_ssofix;
defined('MOODLE_INTERNAL') || die();

final class observer {
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $DB;
        $userid = (int) $event->userid;
        if ($userid <= 0) {
            return;
        }
        $user = $DB->get_record('user', ['id' => $userid], 'id,email,auth,deleted,suspended');
        if (!$user || $user->deleted || $user->suspended || $user->auth !== 'oauth2') {
            return;
        }
        // Uniquement le compte SSO fictif du laboratoire, jamais les autres comptes.
        if (strtolower((string)$user->email) !== 'etudiant-test@groupec.test') {
            return;
        }
        // Le secret est gere par Keycloak; Moodle n'a pas de page de changement
        // de mot de passe OAuth2. Evite le blocage au premier login via SSO.
        if (get_user_preferences('auth_forcepasswordchange', 0, $userid)) {
            unset_user_preference('auth_forcepasswordchange', $userid);
        }
    }
}
