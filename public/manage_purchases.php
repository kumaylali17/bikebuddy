<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Only Admin or Purchasing Manager can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'purchasing_manager'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_purchase'])) {
    
    // Bicycle details
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price_per_day = (float)($_POST['price_per_day'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $image_url = trim($_POST['image_url'] ?? '');
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    
    // Purchase details
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $cost = (float)($_POST['cost'] ?? 0);
    $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
    
    // Validation
    if (empty($name) || $price_per_day <= 0 || empty($branch_id) || empty($supplier_id) || $cost <= 0) {
        $error = "Please fill in all required fields: Name, Price, Branch, Supplier, and Cost.";
    } else {
        
        if (empty($image_url)) {
            $image_url = 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';
        }

        try {
            $pdo->beginTransaction();

            // 1. Insert the new bicycle
            $stmt = $pdo->prepare("
                INSERT INTO bicycle (name, description, price_per_day, category_id, image_url, status, branch_id, supplier_id)
                VALUES (?, ?, ?, ?, ?, 'available', ?, ?)
            ");
            $stmt->execute([
                $name, 
                $description, 
                $price_per_day, 
                $category_id ?: null, 
                $image_url, 
                $branch_id,
                $supplier_id
            ]);
            
            $new_bicycle_id = $pdo->lastInsertId();

            // 2. Log the purchase
            $stmt = $pdo->prepare("
                INSERT INTO purchase (supplier_id, branch_id, bicycle_id, quantity, cost, purchase_date, status)
                VALUES (?, ?, ?, 1, ?, ?, 'completed')
            ");
            $stmt->execute([
                $supplier_id,
                $branch_id,
                $new_bicycle_id,
                $cost,
                $purchase_date
            ]);

            $pdo->commit();
            $_SESSION['success'] = 'New bicycle purchased and added to the system successfully!';
            header('Location: manage_purchases.php');
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Purchase Error: " . $e->getMessage());
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// --- Data Fetching for Form Dropdowns ---
try {
    $suppliers = $pdo->query("SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name")->fetchAll();
    $branches = $pdo->query("SELECT branch_id, name FROM branch ORDER BY name")->fetchAll();
    $categories = $pdo->query("SELECT category_id, name FROM category ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $suppliers = [];
    $branches = [];
    $categories = [];
    $error = "Failed to load form data. " . $e->getMessage();
}

// --- Data Fetching for Purchase List ---
// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    $sql = "
        SELECT 
            p.*,
            b.name as bicycle_name,
            s.supplier_name,
            br.name as branch_name
        FROM purchase p
        JOIN bicycle b ON p.bicycle_id = b.bicycle_id
        JOIN supplier s ON p.supplier_id = s.supplier_id
        JOIN branch br ON p.branch_id = br.branch_id
        ORDER BY p.purchase_date DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $purchases = $stmt->fetchAll();

    $countStmt = $pdo->query("SELECT COUNT(*) FROM purchase");
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $perPage);

} catch (PDOException $e) {
    $purchases = [];
    $total = 0;
    $totalPages = 0;
    $error = "Failed to load purchase history. " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Purchases - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Manage Purchases</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">Purchase New Bicycle</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bicycle Name</label>
                            <input type="text" class="form-control" name="name" placeholder="e.g., Mountain Bike Pro #101" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category_id">
                                <option value="">Select category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Image URL</label>
                            <input type="url" class="form-control" name="image_url" placeholder="https://... (optional)">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rental Price per Day (KES)</label>
                            <input type="number" step="0.01" class="form-control" name="price_per_day" required>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id" required>
                                <option value="">Select supplier...</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="col-md-3 mb-3">
                            <label class="form-label">Branch (Delivery)</label>
                            <select class="form-select" name="branch_id" required>
                                <option value="">Select branch...</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['branch_id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Purchase Cost (KES)</label>
                            <input type="number" step="0.01" class="form-control" name="cost" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="add_purchase" class="btn btn-primary">Add Purchase</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Purchase History</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Purchase ID</th>
                                <th>Date</th>
                                <th>Bicycle</th>
                                <th>Branch</th>
                                <th>Supplier</th>
                                <th>Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($purchases)): ?>
                                <tr><td colspan="6" class="text-center">No purchases logged yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td><strong>#<?= $purchase['purchase_id'] ?></strong></td>
                                <td><?= date('M j, Y', strtotime($purchase['purchase_date'])) ?></td>
                                <td>
                                    <a href="bicycle_details.php?id=<?= $purchase['bicycle_id'] ?>">
                                        <?= htmlspecialchars($purchase['bicycle_name']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($purchase['branch_name']) ?></td>
                                <td><?= htmlspecialchars($purchase['supplier_name']) ?></td>
                                <td>KES <?= number_format($purchase['cost'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>