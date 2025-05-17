<?php
include 'db.php';

$existingIds = isset($_GET['existing_ids']) ? explode(',', $_GET['existing_ids']) : [];

$sql = "
    SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) AS username
    FROM events e
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'Pending' 
";

if (!empty($existingIds)) {
    $placeholders = implode(',', array_fill(0, count($existingIds), '?'));
    $sql .= " AND e.event_id NOT IN ($placeholders)";
}

$stmt = $conn->prepare($sql);

if (!empty($existingIds)) {
    $types = str_repeat('i', count($existingIds));
    $stmt->bind_param($types, ...array_map('intval', $existingIds));
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0):
    while ($event = $result->fetch_assoc()):
?>
        <div class="bg-white p-4 rounded-lg shadow-lg flex gap-4 items-center" data-event-id="<?= $event['event_id'] ?>">
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
            </div>

            <form method="POST" class="space-x-2 flex items-center">
                <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                <button
                    type="submit"
                    name="status"
                    value="Approved"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Approve
                </button>
                <button
                    type="button"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 open-reject-modal"
                    data-event-id="<?= $event['event_id'] ?>">
                    Reject
                </button>
            </form>
        </div>
<?php
    endwhile;
endif;
?>
