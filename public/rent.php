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
$error = '';
$success = '';

// Get available bicycles
try {
    $stmt = $pdo->query("
        SELECT b.*, c.name as category_name
        FROM bicycle b
        LEFT JOIN category c ON b.category_id = c.category_id
        WHERE b.status = 'available'
        ORDER BY b.name
    ");
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

        if (strtotime($start_date) < time()) {
            throw new Exception('Start date cannot be in the past.');
        }

        // Calculate number of days
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $days = $start->diff($end)->days;

        if ($days < 1) {
            $days = 1; // Minimum 1 day
        }

        // Get bicycle price
        $priceStmt = $pdo->prepare("SELECT price_per_day FROM bicycle WHERE bicycle_id = ?");
        $priceStmt->execute([$bicycle_id]);
        $bike = $priceStmt->fetch();

        if (!$bike) {
            throw new Exception('Bicycle not found or not available.');
        }

        $total_cost = $days * $bike['price_per_day'];

        // Create rental record
        $rentalStmt = $pdo->prepare("
            INSERT INTO rental (user_id, bicycle_id, start_date, end_date, total_cost, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $rentalStmt->execute([$user_id, $bicycle_id, $start_date, $end_date, $total_cost]);

        // Update bicycle status
        $updateStmt = $pdo->prepare("UPDATE bicycle SET status = 'rented' WHERE bicycle_id = ?");
        $updateStmt->execute([$bicycle_id]);

        $pdo->commit();

        $_SESSION['success'] = 'Bicycle rented successfully! Total cost: KES ' . number_format($total_cost, 2);
        header('Location: dashboard.php');
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

        <h2 class="mb-4">Available Bicycles</h2>

        <?php if (empty($bicycles)): ?>
            <div class="alert alert-info">
                No bicycles are currently available for rent. Please check back later.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($bicycles as $bike): ?>
                    <div class="col">
                        <div class="card bicycle-card h-100">
                            <?php if ($bike['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($bike['image_url']); ?>"
                                     class="card-img-top bicycle-image"
                                     alt="<?php echo htmlspecialchars($bike['name']); ?>">
                            <?php else: ?>
                                <div class="text-center bg-light p-5">
                                    <i class="bi bi-bicycle" style="font-size: 4rem; color: #6c757d;"></i>
                                </div>
                            <?php endif; ?>

                            <div class="price-tag">
                                KES <?php echo number_format($bike['price_per_day'], 2); ?>/day
                            </div>

                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($bike['name']); ?></h5>
                                <?php if ($bike['category_name']): ?>
                                    <p class="text-muted">Category: <?php echo htmlspecialchars($bike['category_name']); ?></p>
                                <?php endif; ?>
                                <p class="card-text">
                                    <?php echo nl2br(htmlspecialchars($bike['description'] ?? 'No description available.')); ?>
                                </p>

                                <button type="button"
                                        class="btn btn-primary w-100"
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
        // Initialize the modal
        const rentModal = document.getElementById('rentModal');
        if (rentModal) {
            rentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const bikeId = button.getAttribute('data-bike-id');
                const bikeName = button.getAttribute('data-bike-name');
                const pricePerDay = parseFloat(button.getAttribute('data-price'));

                // Set bike info
                document.getElementById('modalBikeId').value = bikeId;
                document.getElementById('bikeName').value = bikeName;

                // Set default dates (today and tomorrow)
                const today = new Date();
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);

                document.getElementById('startDate').value = today.toISOString().split('T')[0];
                document.getElementById('endDate').value = tomorrow.toISOString().split('T')[0];

                // Initial calculation
                updateCost(pricePerDay);

                // Add event listeners for date changes
                document.getElementById('startDate').addEventListener('change', () => updateCost(pricePerDay));
                document.getElementById('endDate').addEventListener('change', () => updateCost(pricePerDay));
            });
        }

        // Calculate and update cost
        function updateCost(pricePerDay) {
            const startDate = new Date(document.getElementById('startDate').value);
            const endDate = new Date(document.getElementById('endDate').value);

            if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
                return;
            }

            // Calculate duration in days (minimum 1 day)
            const timeDiff = endDate.getTime() - startDate.getTime();
            const days = Math.max(1, Math.ceil(timeDiff / (1000 * 3600 * 24)));
            const totalCost = days * pricePerDay;

            // Update UI
            document.getElementById('estimatedCost').textContent = 'KES ' + totalCost.toFixed(2);

            // Format duration text
            document.getElementById('durationText').textContent = `Duration: ${days} day${days !== 1 ? 's' : ''}`;
        }

        // Form validation
        document.getElementById('rentForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('startDate').value);
            const endDate = new Date(document.getElementById('endDate').value);

            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date must be after start date.');
                return false;
            }

            if (startDate < new Date().setHours(0, 0, 0, 0)) {
                e.preventDefault();
                alert('Start date cannot be in the past.');
                return false;
            }

            return true;
        });

        // Set minimum date for start date (today)
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('startDate').min = today;

            // Update minimum end date when start date changes
            document.getElementById('startDate').addEventListener('change', function() {
                document.getElementById('endDate').min = this.value;
            });
        });
    </script>
</body>
</html>
