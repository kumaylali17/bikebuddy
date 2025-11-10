<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict'
]);

require_once __DIR__ . '/../config/db.php';

// Check if user is logged in and is a full admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_branch'])) {
            $stmt = $pdo->prepare("
                INSERT INTO branch (name, location) VALUES (?, ?)
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['location']
            ]);
            $_SESSION['success'] = 'Branch added successfully!';
        } elseif (isset($_POST['update_branch'])) {
            $stmt = $pdo->prepare("
                UPDATE branch SET name = ?, location = ? WHERE branch_id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['location'],
                $_POST['branch_id']
            ]);
            $_SESSION['success'] = 'Branch updated successfully!';
        } elseif (isset($_POST['delete_branch'])) {
            // Check if branch has users or bikes first
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bicycle WHERE branch_id = ?");
            $checkStmt->execute([$_POST['branch_id']]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete branch with assigned bicycles.');
            }
            
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM app_user WHERE branch_id = ?");
            $checkStmt->execute([$_POST['branch_id']]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete branch with assigned users.');
            }

            $stmt = $pdo->prepare("DELETE FROM branch WHERE branch_id = ?");
            $stmt->execute([$_POST['branch_id']]);
            $_SESSION['success'] = 'Branch deleted successfully!';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    header('Location: manage_branches.php');
    exit();
}

// Fetch all branches
try {
    $branches = $pdo->query("SELECT * FROM branch ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching branches: ' . $e->getMessage();
    $branches = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Branches - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Manage Branches</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Add Branch Form -->
        <div class="card mb-4">
            <div class="card-header">Add New Branch</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Branch Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" placeholder="e.g., Nairobi, CBD" required>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" name="add_branch" class="btn btn-primary w-100">Add Branch</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Branch List -->
        <div class="card">
            <div class="card-header">Branch List</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $branch): ?>
                            <tr>
                                <td><?= htmlspecialchars($branch['name']) ?></td>
                                <td><?= htmlspecialchars($branch['location']) ?></td>
                                <td><?= date('M j, Y', strtotime($branch['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal" 
                                            data-id="<?= $branch['branch_id'] ?>"
                                            data-name="<?= htmlspecialchars($branch['name']) ?>"
                                            data-location="<?= htmlspecialchars($branch['location']) ?>">
                                        Edit
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="branch_id" value="<?= $branch['branch_id'] ?>">
                                        <button type="submit" name="delete_branch" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Are you sure? This is permanent.')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Branch</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="branch_id" id="editBranchId">
                        <div class="mb-3">
                            <label class="form-label">Branch Name</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" id="editLocation" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_branch" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit modal
        const editModal = document.getElementById('editModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                
                document.getElementById('editBranchId').value = button.getAttribute('data-id');
                document.getElementById('editName').value = button.getAttribute('data-name');
                document.getElementById('editLocation').value = button.getAttribute('data-location');
            });
        }
    </script>
</body>
</html>