<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/../config/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id']; // User's home branch
$error = '';
$success = '';

// Get available bicycles *at the user's branch*
try {
    $stmt = $pdo->prepare("
        SELECT b.*, c.name as category_name
        FROM bicycle b
        LEFT JOIN category c ON b.category_id = c.category_id
        WHERE b.status = 'available' AND b.branch_id = ?
        ORDER BY b.name
    ");
    $stmt->execute([$user_branch_id]);
    $bicycles = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Available bicycles error: " . $e->getMessage());
    $bicycles = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bicycle_id'])) {
    try {
        $pdo->beginTransaction();

        $bicycle_id = (int)$_POST['bicycle_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        // Validate dates
        if (strtotime($end_date) <= strtotime($start_date)) {
            throw new Exception('End date must be after start date.');
        }

        if (strtotime($start_date) < time() - 3600) { // Allow for small delays
            throw new Exception('Start date cannot be in the past.');
        }

        // Calculate number of days
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $diff = $start->diff($end);
        $days = $diff->days;

        if ($days < 1) {
            $days = 1; // Minimum 1 day
        }

        // Get bicycle price and verify it's at the correct branch
        $priceStmt = $pdo->prepare("
            SELECT price_per_day, branch_id 
            FROM bicycle 
            WHERE bicycle_id = ? AND status = 'available'
        ");
        $priceStmt->execute([$bicycle_id]);
        $bike = $priceStmt->fetch();

        if (!$bike) {
            throw new Exception('Bicycle not found or not available.');
        }
        
        // *** NEW: Verify bike is at the user's branch ***
        if ($bike['branch_id'] != $user_branch_id) {
             throw new Exception('This bicycle is not available at your branch.');
        }

        $total_cost = $days * $bike['price_per_day'];

        // Create rental record
        $rentalStmt = $pdo->prepare("
            INSERT INTO rental (user_id, bicycle_id, start_date, end_date, total_cost, status, start_branch_id)
            VALUES (?, ?, ?, ?, ?, 'active', ?)
        ");
        $rentalStmt->execute([$user_id, $bicycle_id, $start_date, $end_date, $total_cost, $user_branch_id]);

        // Update bicycle status
        $updateStmt = $pdo->prepare("UPDATE bicycle SET status = 'rented' WHERE bicycle_id = ?");
        $updateStmt->execute([$bicycle_id]);

        $pdo->commit();

        $_SESSION['success'] = 'Bicycle rented successfully! Total cost: KES ' . number_format($total_cost, 2);
        header('Location: my_rentals.php');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error processing your request: ' . $e->getMessage();
        error_log($error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent a Bicycle - BikeBuddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .bicycle-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .bicycle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .bicycle-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        .price-tag {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Rent a Bicycle</li>
            </ol>
        </nav>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <h2 class="mb-4">Available Bicycles at <?= htmlspecialchars($_SESSION['branch_name'] ?? 'Your Branch') ?></h2>

        <?php if (empty($bicycles)): ?>
            <div class="alert alert-info">
                No bicycles are currently available for rent at this branch.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($bicycles as $bike): ?>
                    <div class="col">
                        <div class="card bicycle-card h-100">
                            <img src="<?php echo htmlspecialchars($bike['image_url'] ?? 'https://placehold.co/400x300/e2e8f0/64748b?text=No+Image'); ?>"
                                 class="card-img-top bicycle-image"
                                 alt="<?php echo htmlspecialchars($bike['name']); ?>">

                            <div class="price-tag">
                                KES <?php echo number_format($bike['price_per_day'], 2); ?>/day
                            </div>

                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($bike['name']); ?></h5>
                                <?php if ($bike['category_name']): ?>
                                    <p class="text-muted small">Category: <?php echo htmlspecialchars($bike['category_name']); ?></p>
                                <?php endif; ?>
                                <p class="card-text flex-grow-1">
                                    <?php echo nl2br(htmlspecialchars(substr($bike['description'] ?? 'No description.', 0, 100))); ?>
                                    <?php if (strlen($bike['description'] ?? '') > 100): ?>...<?php endif; ?>
                                </p>
                                <button type="button"
                                        class="btn btn-primary w-100 mt-auto"
                                        data-bs-toggle="modal"
                                        data-bs-target="#rentModal"
                                        data-bike-id="<?php echo $bike['bicycle_id']; ?>"
                                        data-bike-name="<?php echo htmlspecialchars($bike['name']); ?>"
                                        data-price="<?php echo $bike['price_per_day']; ?>">
                                    <i class="bi bi-cart-plus"></i> Rent Now
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rent Modal -->
    <div class="modal fade" id="rentModal" tabindex="-1" aria-labelledby="rentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="rentForm" method="POST">
                    <input type="hidden" name="bicycle_id" id="modalBikeId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rentModalLabel">Rent Bicycle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="bikeName" class="form-label">Bicycle</label>
                            <input type="text" class="form-control" id="bikeName" readonly>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="startDate" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDate" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="endDate" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDate" name="end_date" required>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <div class="d-flex justify-content-between">
                                <span>Estimated Cost:</span>
                                <strong id="estimatedCost">KES 0.00</strong>
                            </div>
                            <small class="text-muted" id="durationText"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm Rental</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const rentModal = document.getElementById('rentModal');
        if (rentModal) {
            rentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const bikeId = button.getAttribute('data-bike-id');
                const bikeName = button.getAttribute('data-bike-name');
                const pricePerDay = parseFloat(button.getAttribute('data-price'));

                document.getElementById('modalBikeId').value = bikeId;
                document.getElementById('bikeName').value = bikeName;

                const today = new Date();
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);

                const formatDate = (date) => date.toISOString().split('T')[0];
                document.getElementById('startDate').value = formatDate(today);
                document.getElementById('endDate').value = formatDate(tomorrow);
                document.getElementById('startDate').min = formatDate(today);
                document.getElementById('endDate').min = formatDate(tomorrow);

                updateCost(pricePerDay);

                document.getElementById('startDate').addEventListener('change', () => {
                    const startDate = new Date(document.getElementById('startDate').value);
                    const minEndDate = new Date(startDate);
                    minEndDate.setDate(minEndDate.getDate() + 1);
                    document.getElementById('endDate').min = formatDate(minEndDate);
                    if (new Date(document.getElementById('endDate').value) <= startDate) {
                        document.getElementById('endDate').value = formatDate(minEndDate);
                    }
                    updateCost(pricePerDay);
                });
                document.getElementById('endDate').addEventListener('change', () => updateCost(pricePerDay));
            });
        }

        function updateCost(pricePerDay) {
            const startDate = new Date(document.getElementById('startDate').value);
            const endDate = new Date(document.getElementById('endDate').value);

            if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || endDate <= startDate) {
                document.getElementById('estimatedCost').textContent = 'KES 0.00';
                document.getElementById('durationText').textContent = 'End date must be after start date.';
                return;
            }

            const timeDiff = endDate.getTime() - startDate.getTime();
            const days = Math.max(1, Math.ceil(timeDiff / (1000 * 3600 * 24)));
            const totalCost = days * pricePerDay;

            document.getElementById('estimatedCost').textContent = 'KES ' + totalCost.toFixed(2);
            document.getElementById('durationText').textContent = `Duration: ${days} day${days !== 1 ? 's' : ''}`;
        }
    </script>
</body>
</html>