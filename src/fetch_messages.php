<?php
session_start();
include 'db.php'; // Include your database connection

// Get logged-in user ID
$user_id = $_SESSION['user_id'];

// Get the book owner ID from URL
$book_owner_id = isset($_GET['book_owner_id']) ? (int)$_GET['book_owner_id'] : 0;

if (!$book_owner_id) {
    echo '<p class="text-center text-gray-500 mt-4">Invalid conversation</p>';
    exit;
}

// Fetch the profile picture of the chat user
$user_query = "SELECT profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $book_owner_id);
$stmt->execute();
$user_result = $stmt->get_result();
$chat_user = $user_result->fetch_assoc();
$chat_user_profile_pic = $chat_user['profile_pic'] ?: './assets/055a91979264664a1ee12b9453610d82.jpg';

// Fetch messages between logged-in user and book owner
$messages_query = "SELECT * FROM messages 
                  WHERE (sender_id = ? AND receiver_id = ?) 
                     OR (sender_id = ? AND receiver_id = ?)
                  ORDER BY created_at ASC";
$stmt = $conn->prepare($messages_query);
$stmt->bind_param("iiii", $user_id, $book_owner_id, $book_owner_id, $user_id);
$stmt->execute();
$messages_result = $stmt->get_result();

if ($messages_result && mysqli_num_rows($messages_result) > 0) {
    while ($message = mysqli_fetch_assoc($messages_result)) {
        ?>
        <div class="flex <?= $message['sender_id'] == $user_id ? 'justify-end' : 'justify-start' ?> mb-4">
            <?php if ($message['sender_id'] != $user_id): ?>
                <img src="<?= htmlspecialchars($chat_user_profile_pic) ?>" alt="User" 
                     class="w-8 h-8 rounded-full mr-2 self-end">
            <?php endif; ?>
            <div class="<?= $message['sender_id'] == $user_id ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-800' ?> 
                        rounded-2xl px-4 py-2 max-w-md break-words">
                <?= htmlspecialchars($message['message']) ?>
            </div>
        </div>
        <?php
    }
} else {
    echo '<p class="text-center text-gray-500 mt-4">No messages yet. Start the conversation!</p>';
}
?>