<?php
/**
 * User Logout Handler
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

auth_logout();
redirect('index.php?logged_out=1');
