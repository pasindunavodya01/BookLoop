<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';

$message = '';
$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = intval($_POST['request_id']);
    $enteredOtp = trim($_POST['otp']);
    $userId = $_SESSION['user_id'];

    // Verify that the request exists and the user is the borrower
    $stmt = $conn->prepare("
        SELECT r.return_otp, r.book_id, r.owner_id
        FROM requests r
        WHERE r.request_id = ? AND r.requester_id = ? AND r.status = 'Picked Up'
    ");
    $stmt->bind_param("ii", $requestId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if ($data) {
        if ($data['return_otp'] === $enteredOtp) {
            // Begin transaction to ensure atomic updates
            $conn->begin_transaction();
            try {
                // Update request status to Returned
                $updateStmt = $conn->prepare("UPDATE requests SET status = 'Returned' WHERE request_id = ?");
                $updateStmt->bind_param("i", $requestId);
                $updateStmt->execute();
                $updateStmt->close();

                // Update book status to Available
                $updateBookStmt = $conn->prepare("UPDATE books SET availability_status = 'Available' WHERE book_id = ?");
                $updateBookStmt->bind_param("i", $data['book_id']);
                $updateBookStmt->execute();
                $updateBookStmt->close();

                // Commit transaction
                $conn->commit();

                $message = "<p class='text-green-600 font-semibold'>✅ Return OTP Verified Successfully! Redirecting to the dashboad...</p>";
                header("refresh:3;url=account.php");
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $message = "<p class='text-red-600 font-semibold'>❌ Error processing return: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            $message = "<p class='text-red-600 font-semibold'>❌ Invalid OTP. Please try again.</p>";
        }
    } else {
        $message = "<p class='text-red-600 font-semibold'>❌ Invalid request ID or you are not authorized to verify this OTP.</p>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookLoop - Verify Return OTP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="output.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white shadow-lg rounded-xl p-8 max-w-md w-full">
        <h1 class="text-2xl font-bold text-blue-700 mb-6 text-center">Verify Return OTP</h1>

        <?= $message ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="request_id" value="<?= htmlspecialchars($requestId) ?>">
            <div>
                <label class="block text-gray-700 font-medium">Enter Return OTP:</label>
                <input type="text" name="otp" required class="mt-1 w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="w-full bg-blue-900 text-white py-2 rounded-lg hover:bg-blue-700 transition">Verify</button>
        </form>
    </div>
</body>
</html>