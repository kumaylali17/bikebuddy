<?php
require_once __DIR__ . '/../config/db.php';
session_start();

// Check if user is logged in and is admin
// Per design doc, only main admin or purchasing manager can do this.
// For now, only 'admin' role.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_supplier'])) {
            $stmt = $pdo->prepare("
                INSERT INTO supplier (supplier_name, contact_info)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $_POST['supplier_name'],
                $_POST['contact_info'] ?? ''
            ]);
            $_SESSION['success'] = 'Supplier added successfully!';

        } elseif (isset($_POST['update_supplier'])) {
            $stmt = $pdo->prepare("
                UPDATE supplier
                SET supplier_name = ?, contact_info = ?
                WHERE supplier_id = ?
            ");
            $stmt->execute([
                $_POST['supplier_name'],
                $_POST['contact_info'] ?? '',
                $_POST['supplier_id']
            ]);
            $_SESSION['success'] = 'Supplier updated successfully!';

        } elseif (isset($_POST['delete_supplier'])) {
            $supplier_id = (int)($_POST['supplier_id'] ?? 0);

            // Check if supplier has bicycles
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bicycle WHERE supplier_id = ?");
            $stmt->execute([$supplier_id]);
            $bicycleCount = $stmt->fetchColumn();

            if ($bicycleCount > 0) {
                $_SESSION['error'] = 'Cannot delete supplier with existing bicycles. Please reassign or delete the bicycles first.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM supplier WHERE supplier_id = ?");
                $stmt->execute([$supplier_id]);
                $_SESSION['success'] = 'Supplier deleted successfully!';
            }
        }
    } catch (PDOException $e) {
        error_log('Error managing supplier: ' . $e->getMessage());
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    header('Location: manage_suppliers.php');
    exit();
}

// Fetch all suppliers
try {
    $suppliers = $pdo->query("
        SELECT s.*, (SELECT COUNT(*) FROM bicycle b WHERE b.supplier_id = s.supplier_id) as bicycle_count
        FROM supplier s
        ORDER BY s.supplier_name
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching suppliers: ' . $e->getMessage());
    $suppliers = [];
    $_SESSION['error'] = 'Error accessing suppliers.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Suppliers - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Manage Suppliers</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Add Supplier Form -->
        <div class="card mb-4">
            <div class="card-header">Add New Supplier</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Supplier Name</label>
                        <input type="text" class="form-control" name="supplier_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Info</label>
                        <textarea class="form-control" name="contact_info" rows="3" placeholder="e.g., Phone number, email, address"></textarea>
                    </div>
                    <button type="submit" name="add_supplier" class="btn btn-primary">Add Supplier</button>
                </form>
            </div>
        </div>

        <!-- Suppliers List -->
        <div class="card">
            <div class="card-header">Supplier List</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact Info</th>
                                <th>Bicycles</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td><?= htmlspecialchars($supplier['supplier_name']) ?></td>
                                <td><?= nl2br(htmlspecialchars($supplier['contact_info'] ?? 'N/A')) ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= $supplier['bicycle_count'] ?></span>
                                </td>
                                <td><?= date('M j, Y', strtotime($supplier['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal" 
                                            data-id="<?= $supplier['supplier_id'] ?>"
                                            data-name="<?= htmlspecialchars($supplier['supplier_name']) ?>"
                                            data-contact="<?= htmlspecialchars($supplier['contact_info'] ?? '') ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="supplier_id" value="<?= $supplier['supplier_id'] ?>">
                                        <button type="submit" name="delete_supplier" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Are you sure you want to delete this supplier?')">
                                            <i class="bi bi-trash"></i>
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
                        <h5 class="modal-title">Edit Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="supplier_id" id="editSupplierId">
                        <div class="mb-3">
                            <label class="form-label">Supplier Name</label>
                            <input type="text" class="form-control" name="supplier_name" id="editName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Info</label>
                            <textarea class="form-control" name="contact_info" id="editContactInfo" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_supplier" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editModal = document.getElementById('editModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                
                document.getElementById('editSupplierId').value = button.getAttribute('data-id');
                document.getElementById('editName').value = button.getAttribute('data-name');
                document.getElementById('editContactInfo').value = button.getAttribute('data-contact');
            });
        }
    </script>
</body>
</html>