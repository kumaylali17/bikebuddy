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

// Get rental ID from URL
$rental_id = filter_input(INPUT_GET, 'rental_id', FILTER_VALIDATE_INT);

if (!$rental_id) {
    $_SESSION['error'] = 'Invalid rental ID';
    header('Location: my_rentals.php');
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // 1. Get rental details with bicycle price
    $stmt = $pdo->prepare("
        SELECT r.*, b.price_per_day
        FROM rental r
        JOIN bicycle b ON r.bicycle_id = b.bicycle_id
        WHERE r.id = ? 
        AND r.user_id = ? 
        AND r.return_date IS NULL
        FOR UPDATE
    ");
    $stmt->execute([$rental_id, $_SESSION['user_id']]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rental) {
        throw new Exception('Rental not found or already returned');
    }

    // 2. Calculate total cost
    $return_date = new DateTime();
    $start_date = new DateTime($rental['start_date']);
    $days_rented = $return_date->diff($start_date)->days;
    if ($days_rented < 1) $days_rented = 1; // Minimum 1 day charge
    $total_cost = $days_rented * $rental['price_per_day'];

    // 3. Update rental record
    $stmt = $pdo->prepare("
        UPDATE rental 
        SET return_date = NOW(), 
            total_cost = ?,
            status = 'completed'
        WHERE id = ?
    ");
    $stmt->execute([$total_cost, $rental_id]);

    // 4. Update bicycle status
    $stmt = $pdo->prepare("
        UPDATE bicycle 
        SET status = 'available' 
        WHERE bicycle_id = ?
    ");
    $stmt->execute([$rental['bicycle_id']]);

    // 5. Record payment
    $stmt = $pdo->prepare("
        INSERT INTO payment (rental_id, amount, payment_date, payment_method, status)
        VALUES (?, ?, NOW(), 'cash', 'completed')
    ");
    $stmt->execute([$rental_id, $total_cost]);

    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = sprintf(
        "Bicycle returned successfully! Total cost: KES %s for %d %s",
        number_format($total_cost, 2),
        $days_rented,
        $days_rented === 1 ? 'day' : 'days'
    );

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Return Error: " . $e->getMessage());
    $_SESSION['error'] = "Error processing return: " . $e->getMessage();
}

// Redirect back to rentals page
header('Location: my_rentals.php');
exit();