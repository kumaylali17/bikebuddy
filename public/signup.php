<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            // Check if username or email already exists
            $checkStmt = $pdo->prepare("SELECT user_id FROM app_user WHERE username = :username OR email = :email");
            $checkStmt->execute(['username' => $username, 'email' => $email]);
            
            if ($checkStmt->rowCount() > 0) {
                $error = 'Username or email already exists.';
            } else {
                // *** MODIFIED: Get the first branch_id to assign to new users ***
                // In a real app, you might let them choose, but this is a good default.
                $branchStmt = $pdo->query("SELECT branch_id FROM branch ORDER BY branch_id LIMIT 1");
                $defaultBranch = $branchStmt->fetch();
                
                if (!$defaultBranch) {
                    // This happens if no branches have been created yet.
                    throw new Exception("Registration is currently disabled. Please contact an admin.");
                }
                $defaultBranchId = $defaultBranch['branch_id'];

                // Hash password and create user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO app_user (username, email, password, role, branch_id) 
                    VALUES (:username, :email, :password, 'customer', :branch_id)
                ");
                $stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'branch_id' => $defaultBranchId // Assign the default branch
                ]);
                
                // Log the user in
                $userId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'customer'; // New users are customers
                $_SESSION['branch_id'] = $defaultBranchId; // Set their branch in the session
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            }
        } catch (Exception $e) {
            error_log('Signup error: ' . $e->getMessage());
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign Up - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .signup-container { max-width: 500px; margin: 50px auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="signup-container">
            <h2 class="text-center mb-4">Create an Account</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="signup.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Sign Up</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="login.php">Already have an account? Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>