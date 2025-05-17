<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}


require_once 'db.php';

$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$otp = '';
$error = '';

if ($requestId > 0) {
    // Verify that the user is the owner of the book
    $stmt = $conn->prepare("
        SELECT r.return_otp 
        FROM requests r
        JOIN books b ON r.book_id = b.book_id
        WHERE r.request_id = ? AND b.owner_id = ? AND r.status = 'Picked Up'
    ");
    $stmt->bind_param("ii", $requestId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
        $otp = $data['return_otp'];
    } else {
        $error = "<p class='text-red-600 font-semibold'>❌ Invalid request ID or you are not authorized to view this OTP.</p>";
    }
} else {
    $error = "<p class='text-red-600 font-semibold'>❌ Invalid request ID.</p>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookLoop - View Return OTP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="output.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white shadow-lg rounded-xl p-8 max-w-md w-full">
        <h1 class="text-2xl font-bold text-blue-700 mb-6 text-center">View Return OTP</h1>

        <?php if ($error): ?>
            <?= $error ?>
        <?php elseif ($otp): ?>
            <p class="text-center text-gray-700 mb-4">Please share this OTP with the borrower to confirm the return:</p>
            <div class="bg-gray-100 p-4 rounded-lg text-center">
                <p class="text-2xl font-semibold text-blue-600"><?= htmlspecialchars($otp) ?></p>
            </div>
            <p class="text-center text-gray-600 mt-4">This OTP is required to verify the return of the book.</p>
        <?php endif; ?>

        <a href="account.php" class="block mt-6 text-center bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition">Back to Account</a>
    </div>
</body>
</html>