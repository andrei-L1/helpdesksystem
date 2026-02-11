<?php
// login.php
require_once '../config/dbcon.php';

session_start();

$errors = [];
$old = [];

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Basic rate limiting (per IP/session) - adjust numbers as needed
$max_attempts = 5;
$lockout_minutes = 15;
$attempt_key = 'login_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (!isset($_SESSION[$attempt_key])) {
    $_SESSION[$attempt_key] = ['count' => 0, 'last' => time()];
}

$attempts = &$_SESSION[$attempt_key];

if ($attempts['count'] >= $max_attempts && (time() - $attempts['last']) < ($lockout_minutes * 60)) {
    $remaining = $lockout_minutes - floor((time() - $attempts['last']) / 60);
    $errors[] = "Too many failed attempts. Please wait {$remaining} more minute(s).";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {

    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Security validation failed. Please refresh the page and try again.";
    }

    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $remember   = !empty($_POST['remember']);

    if (empty($identifier) || empty($password)) {
        $errors[] = "Please enter your username/email and password.";
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                SELECT id, username, email, password_hash, first_name, last_name, display_name, role_id,
                       is_active, email_verified, timezone
                FROM users
                WHERE (username = ? OR email = ?) 
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {

                if (!$user['is_active']) {
                    $errors[] = "Your account is currently inactive. Please contact support.";
                } elseif (!$user['email_verified']) {
                    $errors[] = "Please verify your email before logging in.";
                } else {
                    // Successful login
                    $_SESSION['user_id']      = $user['id'];
                    $_SESSION['username']     = $user['username'];
                    $_SESSION['display_name'] = $user['display_name'] ?: trim($user['first_name'] . ' ' . $user['last_name']);
                    $_SESSION['timezone']     = $user['timezone'] ?? 'Asia/Manila';

                    // Update last login time
                    $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                         ->execute([$user['id']]);

                    // Remember me (longer cookie lifetime)
                    if ($remember) {
                        ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // ~30 days
                        session_regenerate_id(true);
                    }

                    // Clear failed attempts counter
                    unset($_SESSION[$attempt_key]);

                    // Redirect after login
                    if($user['role_id'] == 3){
                        header("Location: ../admin/admin_dashboard.php"); // ← CHANGE THIS to your actual admin dashboard/home page
                    } else {
                        header("Location: ../views/dashboard.php"); // ← CHANGE THIS to your actual dashboard/home page
                    }
                    exit;
                }

            } else {
                $errors[] = "Incorrect username/email or password.";
                $attempts['count']++;
                $attempts['last'] = time();
            }

        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again later.";
            error_log("Login failed: " . $e->getMessage());
        }
    }

    // Refresh CSRF token after every POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Keep the identifier field populated on error
    $old['identifier'] = $identifier;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Your Ticketing System</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --danger: #dc2626;
            --gray: #6b7280;
            --light: #f3f4f6;
        }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            margin: 0;
            padding: 20px;
            color: #111827;
        }
        .container {
            max-width: 420px;
            margin: 60px auto;
            background: white;
            padding: 2.2rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        h2 {
            text-align: center;
            margin: 0 0 1.8rem;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        label {
            display: block;
            margin: 1.2rem 0 0.4rem;
            font-weight: 500;
            color: #374151;
        }
        input {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .password-wrapper {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray);
            user-select: none;
        }
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem 0;
            font-size: 0.95rem;
        }
        button {
            margin-top: 1.2rem;
            width: 100%;
            padding: 0.9rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.05rem;
            cursor: pointer;
        }
        button:hover {
            background: var(--primary-dark);
        }
        .center {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--gray);
            font-size: 0.95rem;
        }
        a {
            color: var(--primary);
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Sign In</h2>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <label for="identifier">Username or Email</label>
        <input type="text" name="identifier" id="identifier"
               value="<?= htmlspecialchars($old['identifier'] ?? '') ?>" 
               required autocomplete="username email" autofocus>

        <label for="password">Password</label>
        <div class="password-wrapper">
            <input type="password" name="password" id="password" required autocomplete="current-password">
            <span class="toggle-password" onclick="togglePassword()">Show</span>
        </div>

        <div class="form-options">
            <label>
                <input type="checkbox" name="remember"> Remember me
            </label>
            <a href="forgot-password.php">Forgot password?</a>
        </div>

        <button type="submit">Log In</button>
    </form>

    <div class="center">
        Don't have an account? <a href="register.php">Create one</a>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const toggle = document.querySelector('.toggle-password');
    if (input.type === 'password') {
        input.type = 'text';
        toggle.textContent = 'Hide';
    } else {
        input.type = 'password';
        toggle.textContent = 'Show';
    }
}
</script>

</body>
</html> 