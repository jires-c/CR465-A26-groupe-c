<?php
// Observer Moodle: le login SSO declenche la correction AVANT require_login.
defined('MOODLE_INTERNAL') || die();
$observers = [
    [
        'eventname' => '\core\event\user_loggedin',
        'callback' => '\local_groupec_ssofix\observer::user_loggedin',
        'priority' => 1000,
    ],
];
