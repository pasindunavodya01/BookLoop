<?php
session_start();

require_once 'db.php';

$userId = $_SESSION['user_id'];
$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

$stmt = $conn->prepare("SELECT pickup_otp, return_otp, status FROM requests WHERE request_id = ? AND requester_id = ?");
$stmt->bind_param("ii", $requestId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookLoop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="output.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white shadow-lg rounded-xl p-8 max-w-md text-center">
        <h1 class="text-2xl font-bold text-blue-700 mb-4">Pickup OTP</h1>
        <?php if ($data && $data['status'] === 'Accepted'): ?>
            <p class="text-gray-700 text-lg mb-2">Use this OTP to collect your book:</p>
            <div class="text-4xl font-mono font-bold text-green-600 my-4"><?= htmlspecialchars($data['pickup_otp']) ?></div>
            <a href="account.php" class="block mt-6 text-center bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition">Back to Account</a>
  
        <?php else: ?>
            <p class="text-red-500 font-semibold">OTP not available. The request might not be accepted yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
