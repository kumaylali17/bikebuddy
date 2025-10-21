<?php
require_once __DIR__ . '/../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is admin
$isAdmin = false;
try {
    $stmt = $pdo->prepare("SELECT is_admin FROM app_user WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $isAdmin = $user && $user['is_admin'];
} catch (PDOException $e) {
    error_log('Error checking admin status: ' . $e->getMessage());
    die('An error occurred. Please try again later.');
}

if (!$isAdmin) {
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_bicycle'])) {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price_per_day = (float)($_POST['price_per_day'] ?? 0);
            $supplier_id = (int)($_POST['supplier_id'] ?? 0);
            $image_url = trim($_POST['image_url'] ?? '');

            // Set default image if none provided
            if (empty($image_url)) {
                $image_url = 'https://picsum.photos/400/300?random=' . rand(1, 1000);
            }

            $stmt = $pdo->prepare("
                INSERT INTO bicycle (name, description, price_per_day, supplier_id, image_url, status)
                VALUES (?, ?, ?, ?, ?, 'available')
            ");
            $stmt->execute([$name, $description, $price_per_day, $supplier_id ?: null, $image_url]);

            $_SESSION['success'] = 'Bicycle added successfully!';
            header('Location: manage_bicycles.php');
            exit();
        } elseif (isset($_POST['update_bicycle'])) {
            $bicycle_id = (int)($_POST['bicycle_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price_per_day = (float)($_POST['price_per_day'] ?? 0);
            $supplier_id = (int)($_POST['supplier_id'] ?? 0);
            $image_url = trim($_POST['image_url'] ?? '');

            // Set default image if none provided
            if (empty($image_url)) {
                $image_url = 'https://picsum.photos/400/300?random=' . rand(1, 1000);
            }

            $stmt = $pdo->prepare("
                UPDATE bicycle 
                SET name = ?, description = ?, price_per_day = ?, supplier_id = ?, image_url = ?
                WHERE bicycle_id = ?
            ");
            $stmt->execute([$name, $description, $price_per_day, $supplier_id ?: null, $image_url, $bicycle_id]);

            $_SESSION['success'] = 'Bicycle updated successfully!';
            header('Location: manage_bicycles.php');
            exit();
        } elseif (isset($_POST['delete_bicycle'])) {
            $bicycle_id = (int)($_POST['bicycle_id'] ?? 0);

            $stmt = $pdo->prepare("DELETE FROM bicycle WHERE bicycle_id = ?");
            $stmt->execute([$bicycle_id]);

            $_SESSION['success'] = 'Bicycle deleted successfully!';
            header('Location: manage_bicycles.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        $_SESSION['error'] = 'An error occurred. Please try again.';
    }
}

// Fetch all bicycles with supplier info
try {
    $bicycles = $pdo->query("
        SELECT b.*, 'No Supplier' as supplier_name
        FROM bicycle b
        ORDER BY b.name
    ")->fetchAll();

    // Fetch suppliers (optional - handle if table doesn't exist)
    try {
        $suppliers = $pdo->query("SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name")->fetchAll();
    } catch (PDOException $e) {
        // If supplier table doesn't exist, create it or use empty array
        if (strpos($e->getMessage(), 'supplier') !== false) {
            $suppliers = [];
            // Optionally create supplier table
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS supplier (
                        supplier_id SERIAL PRIMARY KEY,
                        supplier_name VARCHAR(255) NOT NULL,
                        contact_info TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                // Add a default supplier
                try {
                    $pdo->exec("INSERT INTO supplier (supplier_name) VALUES ('Default Supplier')");
                } catch (PDOException $insertError) {
                    // Ignore duplicate key errors
                    if (strpos($insertError->getMessage(), 'duplicate') === false) {
                        throw $insertError;
                    }
                }
                $suppliers = $pdo->query("SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name")->fetchAll();
            } catch (PDOException $e2) {
                $suppliers = [];
            }
        } else {
            $suppliers = [];
        }
    }

    // Fix Road Racer image if it's broken
    try {
        $pdo->exec("UPDATE bicycle SET image_url = 'https://images.unsplash.com/photo-1558618047-3c8c76ca7d13?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&h=300' WHERE name = 'Road Racer' AND image_url LIKE '%bikerumor%'");
    } catch (PDOException $e) {
        // Ignore if update fails
    }
} catch (PDOException $e) {
    error_log('Error fetching data: ' . $e->getMessage());
    $bicycles = [];
    $suppliers = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Bicycles - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Manage Bicycles</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price per Day (KSH)</label>
                            <input type="number" step="0.01" class="form-control" name="price_per_day" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id">
                                <option value="">No Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image URL</label>
                        <input type="url" class="form-control" name="image_url" placeholder="https://example.com/image.jpg" required>
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
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Price/Day (KSH)</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bicycles as $bike): ?>
                            <tr>
                                <td>
                                    <?php if ($bike['image_url']): ?>
                                        <img src="<?= htmlspecialchars($bike['image_url']) ?>" alt="<?= htmlspecialchars($bike['name']) ?>" style="max-width: 100px; max-height: 80px;">
                                    <?php else: ?>
                                        <span class="text-muted">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($bike['name']) ?></td>
                                <td><?= htmlspecialchars($bike['description']) ?></td>
                                <td><?= number_format($bike['price_per_day'], 2) ?></td>
                                <td><?= htmlspecialchars($bike['supplier_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-<?= $bike['status'] === 'available' ? 'success' : 'danger' ?>">
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
                                            data-image="<?= htmlspecialchars($bike['image_url']) ?>">
                                        Edit
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="bicycle_id" value="<?= $bike['bicycle_id'] ?>">
                                        <button type="submit" name="delete_bicycle" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Are you sure you want to delete this bicycle?')">
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
                            <textarea class="form-control" name="description" id="editDescription" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price per Day (KSH)</label>
                                <input type="number" step="0.01" class="form-control" name="price_per_day" id="editPrice" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier</label>
                                <select class="form-select" name="supplier_id" id="editSupplier" required>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image URL</label>
                            <input type="url" class="form-control" name="image_url" id="editImageUrl" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="editStatus" required>
                                <option value="available">Available</option>
                                <option value="unavailable">Unavailable</option>
                                <option value="maintenance">Under Maintenance</option>
                            </select>
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
        // Handle edit modal
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
            });
        }
    </script>
</body>
</html>