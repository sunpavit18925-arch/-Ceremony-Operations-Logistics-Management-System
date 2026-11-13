<?php
session_start();
require 'db.php';

$response = ['role' => 'pending'];

if (isset($_SESSION['username'])) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE username_sso = ?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch();
    if ($user) {
        $response['role'] = $user['role'];
    }
}
echo json_encode($response);