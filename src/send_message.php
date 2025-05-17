<?php
include 'db.php'; // Include the database connection file

// Get logged-in user ID
$user_id = $_SESSION['user_id'];

// Get the book owner ID from the URL
$book_owner_id = $_GET['book_owner_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['message']) && $book_owner_id) {
    // Get the message from the form
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $created_at = date('Y-m-d H:i:s');
    
    // Insert the message into the database
    $query = "INSERT INTO messages (sender_id, receiver_id, message, created_at) 
              VALUES ($user_id, $book_owner_id, '$message', '$created_at')";
    
    if (mysqli_query($conn, $query)) {
        // Redirect back to the chat page after successful message insertion
        header("Location: message.php?book_owner_id=$book_owner_id");
        exit;
    } else {
        echo "Error: " . $query . "<br>" . mysqli_error($conn);
    }
} else {
    echo "Please enter a valid message.";
}

mysqli_close($conn);
?>
