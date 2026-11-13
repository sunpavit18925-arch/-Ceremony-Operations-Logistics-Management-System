<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

require_role([ROLE_HEAD_VEHICLE, ROLE_SUPER_ADMIN]);
header("Location: home_head_vehicle.php");
exit();
