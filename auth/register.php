<?php
require_once '../config/dbcon.php';

session_start();

$errors  = [];
$success = "";
$old     = [];  // only filled on validation failure

// CSRF token generation / refresh
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Fetch default 'user' role ID
try {
    $roleStmt = $conn->prepare("SELECT id FROM roles WHERE name = 'user' LIMIT 1");
    $roleStmt->execute();
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        die("CRITICAL ERROR: Default 'user' role not found in database. Please seed roles table.");
    }
    $default_role_id = $role['id'];
} catch (Exception $e) {
    die("Database connection error: " . htmlspecialchars($e->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Security validation failed. Please refresh and try again.";
    }

    // ── Input collection ────────────────────────────────────────────────────
    $username   = trim($_POST['username']   ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = $_POST['password']        ?? '';
    $password2  = $_POST['password2']       ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $middle     = trim($_POST['middle_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $phone      = trim($_POST['phone']      ?? '');

    // ── Basic validation ────────────────────────────────────────────────────
    if (empty($username) || empty($email) || empty($password) ||
        empty($first_name) || empty($last_name)) {
        $errors[] = "Please complete all required fields (*).";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif ($password !== $password2) {
        $errors[] = "Passwords do not match.";
    }

    // Username format: letters, numbers, . _ - only
    if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) {
        $errors[] = "Username must be 3–60 characters and can contain letters, numbers, dots, underscores or hyphens only.";
    }

    // ── Duplicate check with specific messages ──────────────────────────────
    if (empty($errors)) {
        $stmt = $conn->prepare("
            SELECT username, email 
            FROM users 
            WHERE username = ? OR email = ? 
            LIMIT 1
        ");
        $stmt->execute([$username, $email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (strtolower($existing['username']) === strtolower($username)) {
                $errors[] = "The username <strong>" . htmlspecialchars($username) . "</strong> is already taken.";
            } else {
                $errors[] = "The email <strong>" . htmlspecialchars($email) . "</strong> is already registered.";
            }
        }
    }

    // ── Account creation ────────────────────────────────────────────────────
    if (empty($errors)) {
        try {
            // Prefer Argon2id if available, fallback to bcrypt
            $hash_algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            $password_hash = password_hash($password, $hash_algo);

            // Auto-generate display_name
            $display_name = trim($first_name . ' ' . $last_name);
            if (trim($middle) !== '') {
                $display_name = $first_name . ' ' . mb_substr(trim($middle), 0, 1) . '. ' . $last_name;
            }
            $display_name = trim($display_name);

            $sql = "
                INSERT INTO users (
                    username, email, password_hash,
                    first_name, middle_name, last_name, display_name,
                    phone, role_id,
                    is_active, email_verified,
                    created_at
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?,
                    1, 0,
                    NOW()
                )
            ";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $username,
                $email,
                $password_hash,
                $first_name,
                $middle ?: null,
                $last_name,
                $display_name,
                $phone ?: null,
                $default_role_id
            ]);

            $success = "Account created successfully! You can now log in.";

            // Clear form data after success
            $old = [];

            // Refresh CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Optional redirect (uncomment if preferred):
            // $_SESSION['flash_success'] = $success;
            // header("Location: login.php");
            // exit;

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // duplicate entry
                $errors[] = "Username or email is already in use.";
            } else {
                $errors[] = "An unexpected error occurred. Please try again later.";
                error_log("Registration failed: " . $e->getMessage());
            }
        }
    }

    // Repopulate form only on error
    if (!empty($errors)) {
        $old = $_POST;
    }

    // Always refresh CSRF after POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --danger: #dc2626;
            --success: #059669;
            --gray: #6b7280;
            --light-bg: #f3f4f6;
        }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light-bg);
            margin: 0;
            padding: 20px;
            color: #111827;
        }
        .container {
            max-width: 440px;
            margin: 40px auto;
            background: white;
            padding: 2.2rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        h2 {
            text-align: center;
            margin: 0 0 1.8rem;
            color: #111827;
        }
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        .error  { background: #fee2e2; color: #991b1b; }
        .success { background: #ecfdf5; color: #065f46; }
        label {
            display: block;
            margin: 1.2rem 0 0.4rem;
            font-weight: 500;
            color: #374151;
        }
        .req { color: var(--danger); }
        input, select {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .form-row {
            display: flex;
            gap: 1.2rem;
        }
        .form-row > * { flex: 1; }
        button {
            margin-top: 1.8rem;
            width: 100%;
            padding: 0.9rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.05rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: var(--primary-dark); }
        .note {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.4rem;
        }
        a.link { color: var(--primary); text-decoration: none; font-weight: 500; }
        a.link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h2>Create Account</h2>

    <?php if (!empty($errors)): ?>
        <div class="message error">
            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="message success">
            <?= htmlspecialchars($success) ?>
            <br><br>
            <a href="login.php" class="link">→ Go to Login</a>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <label for="username">Username <span class="req">*</span></label>
        <input type="text" name="username" id="username"
               value="<?= htmlspecialchars($old['username'] ?? '') ?>" required autocomplete="username">

        <label for="email">Email <span class="req">*</span></label>
        <input type="email" name="email" id="email"
               value="<?= htmlspecialchars($old['email'] ?? '') ?>" required autocomplete="email">

        <div class="form-row">
            <div>
                <label for="password">Password <span class="req">*</span></label>
                <input type="password" name="password" id="password" required autocomplete="new-password">
            </div>
            <div>
                <label for="password2">Confirm Password <span class="req">*</span></label>
                <input type="password" name="password2" id="password2" required autocomplete="new-password">
            </div>
        </div>

        <label for="first_name">First Name <span class="req">*</span></label>
        <input type="text" name="first_name" id="first_name"
               value="<?= htmlspecialchars($old['first_name'] ?? '') ?>" required autocomplete="given-name">

        <label for="middle_name">Middle Name</label>
        <input type="text" name="middle_name" id="middle_name"
               value="<?= htmlspecialchars($old['middle_name'] ?? '') ?>" autocomplete="additional-name">

        <label for="last_name">Last Name <span class="req">*</span></label>
        <input type="text" name="last_name" id="last_name"
               value="<?= htmlspecialchars($old['last_name'] ?? '') ?>" required autocomplete="family-name">

        <label for="phone">Phone Number</label>
        <input type="tel" name="phone" id="phone"
               value="<?= htmlspecialchars($old['phone'] ?? '') ?>" autocomplete="tel">

        <button type="submit">Create Account</button>
    </form>
</div>

</body>
</html>