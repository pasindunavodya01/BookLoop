<?php
session_start();
require_once 'db.php';

$message = '';
$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = intval($_POST['request_id']);
    $enteredOtp = trim($_POST['otp']);

    // Check both pickup_otp and return_otp
    $stmt = $conn->prepare("SELECT pickup_otp, return_otp FROM requests WHERE request_id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
        if ($data['pickup_otp'] === $enteredOtp) {
            // Pickup OTP verified
            $message = "<p class='text-green-600 font-semibold'>✅ Pickup OTP Verified Successfully!</p>";
            $updateStmt = $conn->prepare("UPDATE requests SET status = 'Picked Up' WHERE request_id = ?");
            $updateStmt->bind_param("i", $requestId);
            $updateStmt->execute();
            header("refresh:3;url=account.php");
        } elseif ($data['return_otp'] === $enteredOtp) {
            // Return OTP verified
            $message = "<p class='text-green-600 font-semibold'>✅ Return OTP Verified Successfully!</p>";
            $updateStmt = $conn->prepare("UPDATE requests SET status = 'returned' WHERE request_id = ?");
            $updateStmt->bind_param("i", $requestId);
            $updateStmt->execute();
            header("refresh:3;url=account.php");
        } else {
            $message = "<p class='text-red-600 font-semibold'>❌ Invalid OTP. Try again.</p>";
        }
    } else {
        $message = "<p class='text-red-600 font-semibold'>❌ Invalid request ID.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookLoop - Verify OTP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="output.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white shadow-lg rounded-xl p-8 max-w-md w-full">
        <h1 class="text-2xl font-bold text-blue-700 mb-6 text-center">Verify OTP</h1>

        <?= $message ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="request_id" value="<?= $requestId ?>">

            <div>
                <label class="block text-gray-700 font-medium">Enter OTP:</label>
                <input type="text" name="otp" required class="mt-1 w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <button type="submit" class="w-full bg-blue-900 text-black py-2 rounded-lg hover:bg-blue-700 transition">Verify</button>
        </form>
    </div>
</body>
</html>




