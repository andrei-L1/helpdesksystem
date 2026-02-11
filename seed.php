<?php
// seed-admin.php
// Run this file ONCE in browser to create admin user + role + permission

header('Content-Type: text/plain; charset=utf-8');

require_once 'config/dbcon.php';   // ← your PDO / mysqli connection file
// or replace with your actual connection code below:

// ------------------------------
// Option A: Use your existing config (recommended)
// ------------------------------
// require_once __DIR__ . '/config/database.php';
// $pdo = getConnection();   // or however you get PDO instance


// --------------------------------------------------
// Helper function
// --------------------------------------------------
function query($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function insertIfNotExists($conn, $table, $whereColumn, $whereValue, $insertData) {
    $stmt = query($conn, "SELECT 1 FROM $table WHERE $whereColumn = ?", [$whereValue]);
    if ($stmt->fetch()) {
        echo "→ $table with $whereColumn = '$whereValue' already exists → skipped\n";
        return false;
    }

    $columns = implode(', ', array_keys($insertData));
    $placeholders = ':' . implode(', :', array_keys($insertData));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    query($conn, $sql, $insertData);
    echo "→ Created $table: $whereValue\n";
    return true;
}

// --------------------------------------------------
// 1. Create "admin" role
// --------------------------------------------------
$adminRoleId = null;
$roleData = [
    'name'        => 'admin',
    'title'       => 'System Administrator',
    'description' => 'Full access - system management role',
    'is_system'   => 1,
    'sort_order'  => 10,
];

$stmt = query($conn, "SELECT id FROM roles WHERE name = 'admin'");
$row = $stmt->fetch();
if ($row) {
    $adminRoleId = $row['id'];
    echo "→ Role 'admin' already exists (id = $adminRoleId)\n";
} else {
    query($conn,
        "INSERT INTO roles (name, title, description, is_system, sort_order)
         VALUES (:name, :title, :description, :is_system, :sort_order)",
        $roleData
    );
    $adminRoleId = $conn->lastInsertId();
    echo "→ Created admin role (id = $adminRoleId)\n";
}

// --------------------------------------------------
// 2. Create one example permission (manage_users)
// --------------------------------------------------
$permData = [
    'name'     => 'manage_users',
    'title'    => 'Manage Users & Roles',
    'category' => 'Users',
];

insertIfNotExists($conn, 'permissions', 'name', 'manage_users', $permData);

// --------------------------------------------------
// 3. Link permission to admin role
// --------------------------------------------------
$stmt = query($conn,
    "SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = (
        SELECT id FROM permissions WHERE name = 'manage_users'
    )",
    [$adminRoleId]
);
if (!$stmt->fetch()) {
    query($conn,
        "INSERT INTO role_permissions (role_id, permission_id)
         SELECT :role_id, id FROM permissions WHERE name = 'manage_users'",
        ['role_id' => $adminRoleId]
    );
    echo "→ Assigned permission 'manage_users' to admin role\n";
} else {
    echo "→ Permission already assigned to admin role\n";
}

// --------------------------------------------------
// 4. Create admin user
// --------------------------------------------------
$adminUserData = [
    'username'      => 'admin',
    'email'         => 'admin@example.com',
    'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),  // ← CHANGE THIS!
    'first_name'    => 'System',
    'last_name'     => 'Admin',
    'middle_name'   => '',
    'display_name'  => 'Administrator',
    'role_id'       => $adminRoleId,
    'phone'         => null,
    'timezone'      => 'Asia/Manila',
    'is_active'     => 1,
    'email_verified'=> 1,
];

$created = insertIfNotExists($conn, 'users', 'username', 'admin', $adminUserData);

if ($created) {
    echo "\nADMIN ACCOUNT CREATED SUCCESSFULLY!\n";
    echo "Credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123          ← CHANGE IMMEDIATELY AFTER LOGIN!\n";
    echo "  Email:    admin@example.com\n\n";
} else {
    echo "\nAdmin user already exists.\n";
}

echo "Seed script finished.\n";
echo "You should now DELETE or RENAME this file (seed-admin.php) for security reasons!\n";

?>