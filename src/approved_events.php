<?php
require '../vendor/autoload.php';
include 'db.php';

$query = "
    SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) AS username
    FROM events e
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'Approved'
    ORDER BY e.event_date DESC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approved Events | BookLoop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-green-700">Approved Events</h1>
        <div class="space-x-4">
            <a href="admin_dashboard.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Pending Events</a>
            <a href="rejected_events.php" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Rejected Events</a>
        </div>
    </div>

    <div id="event-list" class="space-y-4">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($event = $result->fetch_assoc()): ?>
                <div class="bg-white p-4 rounded-lg shadow-lg flex gap-4 items-center">
                    <?php if (!empty($event['image_url'])): ?>
                        <img src="<?= htmlspecialchars($event['image_url']) ?>" alt="Event Image" class="w-20 h-20 object-cover rounded-lg">
                    <?php else: ?>
                        <div class="w-20 h-20 bg-gray-200 flex items-center justify-center rounded-lg text-sm text-gray-500">No Image</div>
                    <?php endif; ?>

                    <div class="flex-1">
                        <h3 class="text-xl font-semibold"><?= htmlspecialchars($event['event_name']) ?></h3>
                        <p class="text-gray-700"><?= htmlspecialchars($event['description']) ?></p>
                        <p class="text-gray-500"><strong>Date:</strong> <?= $event['event_date'] ?> <strong>Time:</strong> <?= $event['event_time'] ?></p>
                        <p class="text-gray-500"><strong>Venue:</strong> <?= htmlspecialchars($event['venue']) ?></p>
                        <p class="text-gray-500"><strong>Submitted by:</strong> <?= htmlspecialchars($event['username']) ?></p>
                        <p class="text-green-600 font-semibold">Approved</p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-gray-500">No approved events found.</p>
        <?php endif; ?>
    </div>
</body>
</html>