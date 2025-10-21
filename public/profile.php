<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$success = '';
$error = '';

// Get user data
try {
    $stmt = $pdo->prepare("
        SELECT user_id, username, email, phone, address, created_at 
        FROM app_user 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
} catch (PDOException $e) {
    error_log("Profile Error: " . $e->getMessage());
    $error = "An error occurred while loading your profile.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE app_user 
            SET phone = ?, address = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$phone, $address, $_SESSION['user_id']]);
        $success = "Profile updated successfully!";
        
        // Update session data if needed
        $_SESSION['phone'] = $phone;
        
    } catch (PDOException $e) {
        error_log("Profile Update Error: " . $e->getMessage());
        $error = "An error occurred while updating your profile.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-header {
            background-color: #f8f9fa;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid #dee2e6;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['username']) ?>&background=0D8ABC&color=fff" 
                         alt="Profile" class="profile-avatar">
                </div>
                <div class="col">
                    <h1 class="h3 mb-1"><?= htmlspecialchars($user['username']) ?></h1>
                    <p class="text-muted mb-0">Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="profile.php">
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Username</label>
                                <div class="col-sm-9">
                                    <input type="text" readonly class="form-control-plaintext" 
                                           value="<?= htmlspecialchars($user['username']) ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Email</label>
                                <div class="col-sm-9">
                                    <input type="email" readonly class="form-control-plaintext" 
                                           value="<?= htmlspecialchars($user['email']) ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="phone" class="col-sm-3 col-form-label">Phone</label>
                                <div class="col-sm-9">
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="address" class="col-sm-3 col-form-label">Address</label>
                                <div class="col-sm-9">
                                    <textarea class="form-control" id="address" name="address" 
                                              rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-sm-9 offset-sm-3">
                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                    <a href="change_password.php" class="btn btn-outline-secondary">Change Password</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>