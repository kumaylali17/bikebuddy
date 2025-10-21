<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = (int)$_GET['delete'];

    try {
        // Don't allow deleting yourself
        if ($userId === $_SESSION['user_id']) {
            throw new Exception('You cannot delete your own account.');
        }

        // Check if user has active rentals
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM rental WHERE user_id = ? AND status = 'active'");
        $checkStmt->execute([$userId]);
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception('Cannot delete user with active rentals.');
        }

        // Delete user
        $deleteStmt = $pdo->prepare("DELETE FROM app_user WHERE user_id = ?");
        $deleteStmt->execute([$userId]);

        $_SESSION['success'] = 'User deleted successfully.';
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: manage_users.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get users
try {
    $stmt = $pdo->prepare("
        SELECT user_id, username, email, phone, is_admin, created_at, last_login
        FROM app_user
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Manage users error: " . $e->getMessage());
    $users = [];
}

// Get total count
try {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM app_user");
    $total = $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Count error: " . $e->getMessage());
    $total = 0;
}

$totalPages = ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .user-card {
            transition: transform 0.2s;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .admin-badge {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Users</h2>
            <div>
                <span class="text-muted">Total Users: <?php echo $total; ?></span>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (empty($users)): ?>
            <div class="alert alert-info">
                <h4>No users found</h4>
                <p>There are currently no users in the system.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($users as $user): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card user-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="badge bg-warning admin-badge">Admin</span>
                                        <?php endif; ?>
                                    </h5>
                                    <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                        <a href="?delete=<?php echo $user['user_id']; ?>"
                                           class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this user?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-sm-4">Email:</div>
                                    <div class="col-sm-8">
                                        <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </a>
                                    </div>
                                </div>

                                <?php if ($user['phone']): ?>
                                    <div class="row mb-2">
                                        <div class="col-sm-4">Phone:</div>
                                        <div class="col-sm-8"><?php echo htmlspecialchars($user['phone']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <div class="row mb-2">
                                    <div class="col-sm-4">Joined:</div>
                                    <div class="col-sm-8">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </div>
                                </div>

                                <?php if ($user['last_login']): ?>
                                    <div class="row mb-2">
                                        <div class="col-sm-4">Last Login:</div>
                                        <div class="col-sm-8">
                                            <?php echo date('M j, Y H:i', strtotime($user['last_login'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-3">
                                    <a href="profile.php?user_id=<?php echo $user['user_id']; ?>"
                                       class="btn btn-outline-primary btn-sm">
                                        View Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Users pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</body>
</html>
