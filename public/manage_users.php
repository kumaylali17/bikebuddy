<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Only the main 'admin' can manage users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submission for role/branch update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_role'])) {
    try {
        $user_id = (int)$_POST['user_id'];
        $role = (string)$_POST['role'];
        // Use null if the branch_id is empty (e.g., for 'admin' or 'customer')
        $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;

        // Prevent admin from demoting themselves
        if ($user_id === $_SESSION['user_id'] && $role !== 'admin') {
            throw new Exception('You cannot change your own role.');
        }

        // Only branch_managers should have a branch_id
        if ($role !== 'branch_manager') {
            $branch_id = null;
        } elseif (empty($branch_id)) {
            throw new Exception('A Branch Manager must be assigned to a branch.');
        }

        $stmt = $pdo->prepare("UPDATE app_user SET role = ?, branch_id = ? WHERE user_id = ?");
        $stmt->execute([$role, $branch_id, $user_id]);
        $_SESSION['success'] = 'User role updated successfully.';

    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    header('Location: manage_users.php');
    exit();
}

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = (int)$_GET['delete'];

    try {
        if ($userId === $_SESSION['user_id']) {
            throw new Exception('You cannot delete your own account.');
        }
        
        // Get user role before deleting
        $roleStmt = $pdo->prepare("SELECT role FROM app_user WHERE user_id = ?");
        $roleStmt->execute([$userId]);
        $userToDelete = $roleStmt->fetch();

        if ($userToDelete && $userToDelete['role'] === 'admin') {
             throw new Exception('You cannot delete another admin account.');
        }

        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM rental WHERE user_id = ? AND status = 'active'");
        $checkStmt->execute([$userId]);
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception('Cannot delete user with active rentals.');
        }

        $deleteStmt = $pdo->prepare("DELETE FROM app_user WHERE user_id = ?");
        $deleteStmt->execute([$userId]);
        $_SESSION['success'] = 'User deleted successfully.';

    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    header('Location: manage_users.php');
    exit();
}

// --- Data Fetching ---

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get users with branch name
try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.email, u.phone, u.role, u.created_at, u.last_login, u.branch_id, b.name as branch_name
        FROM app_user u
        LEFT JOIN branch b ON u.branch_id = b.branch_id
        ORDER BY u.created_at DESC
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

// Get branches for dropdown
try {
    $branches = $pdo->query("SELECT branch_id, name FROM branch ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $branches = [];
}

// Define roles
$roles = ['customer', 'branch_manager', 'admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .admin-badge { font-size: 0.8em; }
        .manager-badge { font-size: 0.8em; }
        .customer-badge { font-size: 0.8em; }
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
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email / Phone</th>
                                <th>Role</th>
                                <th>Branch</th>
                                <th>Joined</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                        <td>
                                            <div><?= htmlspecialchars($user['email']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = 'secondary';
                                            if ($user['role'] === 'admin') $badgeClass = 'danger';
                                            if ($user['role'] === 'branch_manager') $badgeClass = 'warning';
                                            if ($user['role'] === 'customer') $badgeClass = 'primary';
                                            ?>
                                            <span class="badge bg-<?= $badgeClass ?>"><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($user['branch_name'] ?? 'N/A') ?>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Never' ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#roleModal"
                                                    data-user-id="<?= $user['user_id'] ?>"
                                                    data-username="<?= htmlspecialchars($user['username']) ?>"
                                                    data-role="<?= $user['role'] ?>"
                                                    data-branch-id="<?= $user['branch_id'] ?? '' ?>">
                                                <i class="bi bi-person-gear"></i> Edit Role
                                            </button>
                                            
                                            <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                                <a href="?delete=<?= $user['user_id'] ?>"
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to delete this user?')">
                                                   <i class="bi bi-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
    </div>

    <!-- Edit Role Modal -->
    <div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="roleModalLabel">Edit User Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="modalUserId">
                        <p>User: <strong id="modalUsername"></strong></p>
                        
                        <div class="mb-3">
                            <label for="modalRole" class="form-label">Role</label>
                            <select class="form-select" id="modalRole" name="role" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role ?>"><?= ucfirst(str_replace('_', ' ', $role)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="branchSelectContainer" style="display: none;">
                            <label for="modalBranch" class="form-label">Branch</label>
                            <select class="form-select" id="modalBranch" name="branch_id">
                                <option value="">Select a branch...</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['branch_id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_user_role" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const roleModal = document.getElementById('roleModal');
        const branchSelectContainer = document.getElementById('branchSelectContainer');
        const modalRoleSelect = document.getElementById('modalRole');

        if (roleModal) {
            roleModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                
                // Populate modal
                document.getElementById('modalUserId').value = button.getAttribute('data-user-id');
                document.getElementById('modalUsername').textContent = button.getAttribute('data-username');
                
                const role = button.getAttribute('data-role');
                modalRoleSelect.value = role;
                
                document.getElementById('modalBranch').value = button.getAttribute('data-branch-id');
                
                // Show/hide branch dropdown
                toggleBranchSelect(role);
            });

            modalRoleSelect.addEventListener('change', function() {
                toggleBranchSelect(this.value);
            });
        }

        function toggleBranchSelect(role) {
            if (role === 'branch_manager') {
                branchSelectContainer.style.display = 'block';
                document.getElementById('modalBranch').required = true;
            } else {
                branchSelectContainer.style.display = 'none';
                document.getElementById('modalBranch').required = false;
            }
        }
    </script>
</body>
</html>