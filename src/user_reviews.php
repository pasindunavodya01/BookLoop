<?php
include 'db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Get individual reviews
$sql = "SELECT ur.rating, ur.review, ur.created_at, 
               rater.first_name AS rater_first_name, rater.last_name AS rater_last_name
        FROM user_ratings ur
        JOIN users rater ON ur.rater_user_id = rater.id
        WHERE ur.rated_user_id = ?
        ORDER BY ur.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Get average rating
$avg_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
            FROM user_ratings 
            WHERE rated_user_id = ?";
$avg_stmt = $conn->prepare($avg_sql);
$avg_stmt->bind_param("i", $user_id);
$avg_stmt->execute();
$avg_result = $avg_stmt->get_result()->fetch_assoc();

$average_rating = round($avg_result['avg_rating'] ?? 0, 1);
$review_count = $avg_result['review_count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Reviews</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .rating-container {
            width: 100%;
            max-width: 600px; /* Wider than the content below */
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold mb-2 text-blue-700 text-center">User Reviews</h1>
        
        <!-- Wider Average Rating Display -->
        <div class="rating-container bg-white p-6 rounded-lg shadow-lg mb-8">
            <div class="flex flex-col items-center justify-center">
                <div class="text-5xl font-bold text-blue-600 mb-2"><?= $average_rating ?></div>
                <div class="text-yellow-500 text-3xl mb-3">
                    <?php 
                    $full_stars = floor($average_rating);
                    $has_half_star = ($average_rating - $full_stars) >= 0.5;
                    
                    for ($i = 0; $i < $full_stars; $i++): ?>
                        ★
                    <?php endfor; ?>
                    
                    <?php if ($has_half_star): ?>
                        ⯪
                    <?php endif; ?>
                    
                    <?php 
                    $empty_stars = 5 - $full_stars - ($has_half_star ? 1 : 0);
                    for ($i = 0; $i < $empty_stars; $i++): ?>
                        ☆
                    <?php endfor; ?>
                </div>
                <div class="text-gray-600 text-lg">
                    Based on <?= $review_count ?> review<?= $review_count != 1 ? 's' : '' ?>
                </div>
            </div>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="bg-white p-5 rounded-lg shadow mb-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            Reviewed by: 
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($row['rater_first_name'] . ' ' . $row['rater_last_name']) ?></span>
                        </div>
                        <div class="text-yellow-500 font-bold">
                            <?php for ($i = 0; $i < $row['rating']; $i++): ?>
                                ★
                            <?php endfor; ?>
                            <?php for ($i = $row['rating']; $i < 5; $i++): ?>
                                ☆
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p class="mt-3 text-gray-700"><?= nl2br(htmlspecialchars($row['review'])) ?></p>
                    <p class="text-sm text-gray-400 mt-2">Posted on <?= date("F j, Y, g:i a", strtotime($row['created_at'])) ?></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-yellow-100 text-yellow-700 p-4 rounded">
                No reviews found for this user.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>