<?php
/**
 * Single Sign-On (SSO / OIDC) Service
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

if (!function_exists('sso_generate_auth_url')) {
    function sso_generate_auth_url(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => CLIENT_ID,
            'redirect_uri'  => REDIRECT_URL,
            'scope'         => SCOPE,
            'state'         => $state
        ]);

        return SSO_BASE_URL . '/authorize?' . $params;
    }
}

if (!function_exists('sso_handle_callback')) {
    /**
     * Process SSO Callback with CSRF state check and secure TLS verification
     *
     * @param string $code
     * @param string $state
     * @return array [bool $success, string $message, ?array $user]
     */
    function sso_handle_callback(string $code, string $state): array
    {
        // 1. Verify OAuth State against Session
        $sessionState = $_SESSION['oauth_state'] ?? '';
        if (empty($sessionState) || empty($state) || !hash_equals($sessionState, $state)) {
            return [false, 'State ไม่ตรงกันหรือเซสชันหมดอายุ (อาจเป็นการโจมตีแบบ CSRF)', null];
        }

        // Clear used state to prevent replay
        unset($_SESSION['oauth_state']);

        if (empty($code)) {
            return [false, 'ไม่ได้รับ Authorization Code จากระบบ SSO', null];
        }

        // 2. Request Access Token
        $tokenUrl = SSO_BASE_URL . '/token';
        $tokenPayload = json_encode([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => REDIRECT_URL,
            'client_id'     => CLIENT_ID,
            'client_secret' => CLIENT_SECRET,
            'response_type' => 'code',
            'state'         => $state,
            'scope'         => SCOPE
        ]);

        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $tokenPayload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $tokenResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('SSO Token Request cURL Error: ' . $curlError);
            return [false, 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ SSO ได้ (Network/TLS Error)', null];
        }

        if ($httpCode !== 200 || empty($tokenResponse)) {
            error_log("SSO Token HTTP {$httpCode}: " . substr((string)$tokenResponse, 0, 200));
            return [false, "เกิดข้อผิดพลาดในการขอ Access Token จากระบบ SSO (HTTP Code: {$httpCode})", null];
        }

        $tokenJson = json_decode($tokenResponse, true);
        $accessToken = $tokenJson['access_token'] ?? '';
        $idToken = $tokenJson['id_token'] ?? '';

        if (empty($accessToken)) {
            return [false, 'ไม่พบ Access Token ในการตอบกลับจากระบบ SSO', null];
        }

        // 3. Request User Info
        $userInfoUrl = SSO_BASE_URL . '/userinfo';
        $userPayload = json_encode([
            'client_id'     => CLIENT_ID,
            'response_type' => 'code',
            'state'         => $state,
            'scope'         => SCOPE
        ]);

        // DPT SSO documentation specifies Bearer with accessToken or idToken
        $bearerToken = !empty($idToken) ? $idToken : $accessToken;

        $chUser = curl_init($userInfoUrl);
        curl_setopt_array($chUser, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $userPayload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $bearerToken,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $userResponse = curl_exec($chUser);
        $userHttpCode = curl_getinfo($chUser, CURLINFO_HTTP_CODE);
        $userCurlError = curl_error($chUser);
        curl_close($chUser);

        if ($userCurlError) {
            error_log('SSO UserInfo cURL Error: ' . $userCurlError);
            return [false, 'ไม่สามารถดึงข้อมูลผู้ใช้งานจาก SSO ได้ (Network Error)', null];
        }

        if ($userHttpCode !== 200 || empty($userResponse)) {
            error_log("SSO UserInfo HTTP {$userHttpCode}: " . substr((string)$userResponse, 0, 200));
            return [false, "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้งาน (HTTP Code: {$userHttpCode})", null];
        }

        $userData = json_decode($userResponse, true);
        if (!is_array($userData)) {
            return [false, 'รูปแบบข้อมูลผู้ใช้จาก SSO ไม่ถูกต้อง', null];
        }

        $usernameSso = trim((string)($userData['sub'] ?? $userData['user_id'] ?? ''));
        if (empty($usernameSso)) {
            return [false, 'ไม่พบรหัสผู้ใช้งาน (sub / user_id) จาก SSO', null];
        }

        $fname = clean_string($userData['given_name_th'] ?? $userData['given_name'] ?? $userData['fname'] ?? '', 100);
        $lname = clean_string($userData['family_name_th'] ?? $userData['family_name'] ?? $userData['lname'] ?? '', 100);
        $email = clean_string($userData['email'] ?? '', 150);
        $mobile = clean_string($userData['phone_number'] ?? $userData['phone'] ?? '', 50);
        $cid = clean_string($userData['identification_number'] ?? $userData['idcard'] ?? '', 20);
        $deptName = clean_string($userData['department_name'] ?? '', 255);
        $deptCode = clean_string($userData['dept_code'] ?? '', 20);

        // 4. Safe Account Matching
        $conn = get_db_connection();
        $user = null;

        // Step 4.1: Match strictly by unique username_sso
        $stmt = $conn->prepare("SELECT * FROM users WHERE username_sso = ? LIMIT 1");
        $stmt->execute([$usernameSso]);
        $user = $stmt->fetch();

        // Step 4.2: Match by CID only if CID is not empty and username_sso was not matched
        if (!$user && !empty($cid)) {
            $stmtCid = $conn->prepare("SELECT * FROM users WHERE cid = ? AND cid != '' LIMIT 1");
            $stmtCid->execute([$cid]);
            $user = $stmtCid->fetch();
        }

        if ($user) {
            // Update existing user profile without clearing valid existing data
            $updateSql = "UPDATE users SET 
                fname = COALESCE(NULLIF(?, ''), fname),
                lname = COALESCE(NULLIF(?, ''), lname),
                email = COALESCE(NULLIF(?, ''), email),
                mobile_phone = COALESCE(NULLIF(?, ''), mobile_phone),
                cid = COALESCE(NULLIF(?, ''), cid),
                depart_name = COALESCE(NULLIF(?, ''), depart_name),
                depart_code = COALESCE(NULLIF(?, ''), depart_code)
                WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([$fname, $lname, $email, $mobile, $cid, $deptName, $deptCode, $user['id']]);

            $userId = (int)$user['id'];
            $userRole = $user['role'];
            $userCid = !empty($cid) ? $cid : ($user['cid'] ?? '');
        } else {
            // New user registration -> assign default 'pending' role
            $insertSql = "INSERT INTO users (username_sso, fname, lname, email, mobile_phone, cid, depart_name, depart_code, role)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->execute([$usernameSso, $fname, $lname, $email, $mobile, $cid, $deptName, $deptCode]);

            $userId = (int)$conn->lastInsertId();
            $userRole = 'pending';
            $userCid = $cid;
        }

        // Regenerate Session ID on successful login to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $userRole;
        $_SESSION['username'] = $usernameSso;
        $_SESSION['username_sso'] = $usernameSso;
        $_SESSION['user_fname'] = $fname;
        $_SESSION['fname'] = $fname;
        $_SESSION['lname'] = $lname;
        $_SESSION['email'] = $email;
        $_SESSION['mobile_phone'] = $mobile;
        $_SESSION['cid'] = $userCid;
        $_SESSION['depart_name'] = $deptName;
        $_SESSION['depart_code'] = $deptCode;
        $_SESSION['access_token'] = $accessToken;
        $_SESSION['last_activity'] = time();

        return [true, 'เข้าสู่ระบบสำเร็จ', ['id' => $userId, 'role' => $userRole]];
    }
}

