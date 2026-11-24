<?php
/**
 * DPT SSO Login Redirect Handler
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/sso.php';

$authUrl = sso_generate_auth_url();
header('Location: ' . $authUrl);
exit();
