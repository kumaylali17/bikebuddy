<?php
require_once __DIR__ . '/../config/db.php';
session_start();

// Check if user is logged in and has an admin-level role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'branch_manager'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_bicycle'])) {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price_per_day = (float)($_POST['price_per_day'] ?? 0);
            $supplier_id = (int)($_POST['supplier_id'] ?? 0);
            $image_url = trim($_POST['image_url'] ?? '');
            $branch_id = (int)($_POST['branch_id'] ?? 0);

            // Admin must select a branch. Branch Manager is locked to their branch.
            if ($user_role === 'admin' && empty($branch_id)) {
                throw new Exception("Admin must select a branch.");
            } elseif ($user_role === 'branch_manager') {
                $branch_id = $user_branch_id;
            }

            if (empty($image_url)) {
                $image_url = 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';
            }

            $stmt = $pdo->prepare("
                INSERT INTO bicycle (name, description, price_per_day, supplier_id, image_url, status, branch_id)
                VALUES (?, ?, ?, ?, ?, 'available', ?)
            ");
            $stmt->execute([$name, $description, $price_per_day, $supplier_id ?: null, $image_url, $branch_id]);

            $_SESSION['success'] = 'Bicycle added successfully!';

        } elseif (isset($_POST['update_bicycle'])) {
            $bicycle_id = (int)($_POST['bicycle_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price_per_day = (float)($_POST['price_per_day'] ?? 0);
            $supplier_id = (int)($_POST['supplier_id'] ?? 0);
            $image_url = trim($_POST['image_url'] ?? '');
            $branch_id = (int)($_POST['branch_id'] ?? 0);
            $status = (string)($_POST['status'] ?? 'available');

            if (empty($image_url)) {
                $image_url = 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';
            }
            
            // Check authorization
            $authCheck = $pdo->prepare("SELECT branch_id FROM bicycle WHERE bicycle_id = ?");
            $authCheck->execute([$bicycle_id]);
            $bike = $authCheck->fetch();

            if (!$bike || ($user_role === 'branch_manager' && $bike['branch_id'] != $user_branch_id)) {
                throw new Exception("You are not authorized to edit this bicycle.");
            }

            // Branch manager cannot change the branch, admin can.
            $sql = "UPDATE bicycle SET name = ?, description = ?, price_per_day = ?, supplier_id = ?, image_url = ?, status = ?";
            $params = [$name, $description, $price_per_day, $supplier_id ?: null, $image_url, $status];

            if ($user_role === 'admin') {
                $sql .= ", branch_id = ?";
                $params[] = $branch_id;
            }
            $sql .= " WHERE bicycle_id = ?";
            $params[] = $bicycle_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['success'] = 'Bicycle updated successfully!';

        } elseif (isset($_POST['delete_bicycle'])) {
            $bicycle_id = (int)($_POST['bicycle_id'] ?? 0);

            // Check authorization
            $authCheck = $pdo->prepare("SELECT branch_id FROM bicycle WHERE bicycle_id = ?");
            $authCheck->execute([$bicycle_id]);
            $bike = $authCheck->fetch();

            if (!$bike || ($user_role === 'branch_manager' && $bike['branch_id'] != $user_branch_id)) {
                throw new Exception("You are not authorized to delete this bicycle.");
            }
            
            // TODO: Check for active rentals before deleting

            $stmt = $pdo->prepare("DELETE FROM bicycle WHERE bicycle_id = ?");
            $stmt->execute([$bicycle_id]);

            $_SESSION['success'] = 'Bicycle deleted successfully!';
        }
    } catch (Exception $e) {
        error_log('Bicycle Mgt Error: ' . $e->getMessage());
        $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
    }
    header('Location: manage_bicycles.php');
    exit();
}

// Fetch suppliers
try {
    $suppliers = $pdo->query("SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name")->fetchAll();
} catch (PDOException $e) {
    $suppliers = [];
}

// Fetch branches (for admin)
try {
    $branches = $pdo->query("SELECT branch_id, name FROM branch ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $branches = [];
}

// Fetch bicycles
try {
    $sql = "
        SELECT b.*, s.supplier_name, br.name as branch_name
        FROM bicycle b
        LEFT JOIN supplier s ON b.supplier_id = s.supplier_id
        LEFT JOIN branch br ON b.branch_id = br.branch_id
    ";
    $params = [];

    if ($user_role === 'branch_manager') {
        $sql .= " WHERE b.branch_id = ?";
        $params[] = $user_branch_id;
    }
    
    $sql .= " ORDER BY b.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bicycles = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Error fetching data: ' . $e->getMessage());
    $bicycles = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Bicycles - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Manage Bicycles</h2>
        <?php if ($user_role === 'branch_manager'): ?>
            <h5 class="text-muted">My Branch: <?= htmlspecialchars($_SESSION['branch_name'] ?? 'N/A') ?></h5>
        <?php endif; ?>
        
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

        <!-- Add Bicycle Form -->
        <div class="card mb-4">
            <div class="card-header">Add New Bicycle</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price per Day (KSH)</label>
                            <input type="number" step="0.01" class="form-control" name="price_per_day" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id">
                                <option value="">No Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Branch</label>
                            <?php if ($user_role === 'admin'): ?>
                                <select class="form-select" name="branch_id" required>
                                    <option value="">Select branch...</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['branch_id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="hidden" name="branch_id" value="<?= $user_branch_id ?>">
                                <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['branch_name'] ?? 'Your Branch') ?>" readonly>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image URL</label>
                        <input type="url" class="form-control" name="image_url" placeholder="https://example.com/image.jpg (optional)">
                    </div>
                    <button type="submit" name="add_bicycle" class="btn btn-primary">Add Bicycle</button>
                </form>
            </div>
        </div>

        <!-- Bicycles List -->
        <div class="card">
            <div class="card-header">Bicycle List</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Branch</th>
                                <th>Price/Day</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bicycles as $bike): ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($bike['image_url']) ?>" alt="<?= htmlspecialchars($bike['name']) ?>" style="width: 100px; height: 75px; object-fit: cover; border-radius: 4px;">
                                </td>
                                <td><?= htmlspecialchars($bike['name']) ?></td>
                                <td><strong><?= htmlspecialchars($bike['branch_name'] ?? 'N/A') ?></strong></td>
                                <td><?= number_format($bike['price_per_day'], 2) ?></td>
                                <td><?= htmlspecialchars($bike['supplier_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-<?= $bike['status'] === 'available' ? 'success' : ($bike['status'] === 'rented' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($bike['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal" 
                                            data-id="<?= $bike['bicycle_id'] ?>"
                                            data-name="<?= htmlspecialchars($bike['name']) ?>"
                                            data-description="<?= htmlspecialchars($bike['description']) ?>"
                                            data-price="<?= $bike['price_per_day'] ?>"
                                            data-supplier="<?= $bike['supplier_id'] ?>"
                                            data-image="<?= htmlspecialchars($bike['image_url']) ?>"
                                            data-status="<?= $bike['status'] ?>"
                                            data-branch-id="<?= $bike['branch_id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="bicycle_id" value="<?= $bike['bicycle_id'] ?>">
                                        <button type="submit" name="delete_bicycle" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Are you sure you want to delete this bicycle?')">
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Bicycle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="bicycle_id" id="editBicycleId">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editDescription" required rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Price per Day (KSH)</label>
                                <input type="number" step="0.01" class="form-control" name="price_per_day" id="editPrice" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Supplier</label>
                                <select class="form-select" name="supplier_id" id="editSupplier">
                                    <option value="">No Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Branch</label>
                                <?php if ($user_role === 'admin'): ?>
                                    <select class="form-select" name="branch_id" id="editBranch" required>
                                        <option value="">Select branch...</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?= $branch['branch_id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="hidden" name="branch_id" id="editBranch">
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['branch_name'] ?? 'Your Branch') ?>" readonly>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Image URL</label>
                                <input type="url" class="form-control" name="image_url" id="editImageUrl">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="editStatus" required>
                                    <option value="available">Available</option>
                                    <option value="rented">Rented</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_bicycle" class="btn btn-primary">Save changes</button>
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
                
                document.getElementById('editBicycleId').value = button.getAttribute('data-id');
                document.getElementById('editName').value = button.getAttribute('data-name');
                document.getElementById('editDescription').value = button.getAttribute('data-description');
                document.getElementById('editPrice').value = button.getAttribute('data-price');
                document.getElementById('editSupplier').value = button.getAttribute('data-supplier');
                document.getElementById('editImageUrl').value = button.getAttribute('data-image');
                document.getElementById('editStatus').value = button.getAttribute('data-status');
                document.getElementById('editBranch').value = button.getAttribute('data-branch-id');
            });
        }
    </script>
</body>
</html>