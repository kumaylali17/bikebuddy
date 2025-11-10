<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Debug: Log session start
error_log("Session started: " . session_id());

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Debug: Log login attempt
            error_log("Login attempt for user: $username");
            
            // Get user from database
            // *** MODIFIED: Fetch 'role' and 'branch_id' instead of 'is_admin' ***
            $stmt = $pdo->prepare("
                SELECT user_id, username, password, role, branch_id 
                FROM app_user 
                WHERE username = :username
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            
            // Debug: Log user data
            error_log("User data: " . print_r($user, true));
            
            if ($user) {
                // Debug: Log password verification
                $passwordMatch = password_verify($password, $user['password']);
                error_log("Password verification: " . ($passwordMatch ? "Match" : "No match"));
                
                if ($passwordMatch) {
                    // Update last login
                    $updateStmt = $pdo->prepare("
                        UPDATE app_user 
                        SET last_login = NOW() 
                        WHERE user_id = :user_id
                    ");
                    $updateStmt->execute(['user_id' => $user['user_id']]);
                    
                    // Set session variables
                    // *** MODIFIED: Set new session variables ***
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['branch_id'] = $user['branch_id'];
                    
                    // Debug: Log session data
                    error_log("Session data set - User ID: {$_SESSION['user_id']}, Role: {$_SESSION['role']}, Branch: {$_SESSION['branch_id']}");
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit();
                }
            }
            
            // If we get here, login failed
            $error = 'Invalid username or password.';
            error_log("Login failed for user: $username");
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin: 100px auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h2 class="text-center mb-4">BikeBuddy Login</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="signup.php">Don't have an account? Sign up</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>