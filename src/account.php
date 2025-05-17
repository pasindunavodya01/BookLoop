<?php
session_start();
include 'db.php'; // Include the database connection file

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$sql = "SELECT first_name, last_name, profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
$updateMessage = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $password = trim($_POST['password']);
    $profile_picture = $user['profile_pic'];

    // Validate inputs
    if (empty($first_name) || empty($last_name)) {
        $updateMessage = "❌ First name and last name are required.";
    } else {
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowed_types = ['image/jpeg', 'image/png','image/jpeg','image/webp', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                $upload_dir = 'Uploads/';
                $upload_path = $upload_dir . $filename;

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $profile_picture = $upload_path;
                } else {
                    $updateMessage = "❌ Failed to upload profile picture.";
                    echo "<script>
                    setTimeout(function() {
                        window.location.href = 'account.php';
                    }, 5000);
                </script>";
                }
            } else {
                $updateMessage = "❌ Invalid file type or size. Use JPEG, PNG, or GIF under 2MB.";
                echo "<script>
                setTimeout(function() {
                    window.location.href = 'account.php';
                }, 5000);
            </script>";
            }
        }

        if (empty($updateMessage)) {
            // Update user details
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET first_name = ?, last_name = ?, password = ?, profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $first_name, $last_name, $hashed_password, $profile_picture, $user_id);
            } else {
                $sql = "UPDATE users SET first_name = ?, last_name = ?, profile_pic = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $first_name, $last_name, $profile_picture, $user_id);
            }

            if ($stmt->execute()) {
                $updateMessage = "✅ Profile updated successfully.";
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'account.php';
                    }, 5000);
                </script>";
                // Refresh user data
                $sql = "SELECT first_name, last_name, profile_pic FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $updateMessage = "❌ Error updating profile: " . $stmt->error;
            }
        }
    }
}

require_once 'get_unread_messages.php';
$unreadCount = getUnreadMessagesCount($conn, $_SESSION['user_id']);

// Fetch requested books by the logged-in user (Pending requests)
$sql = "
    SELECT b.book_id, b.title, b.author, b.image_url, r.status, r.request_id 
    FROM books b
    JOIN requests r ON b.book_id = r.book_id
    WHERE r.requester_id = ? AND r.status = 'Pending'
    ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$pendingRequestedBooks = $result->fetch_all(MYSQLI_ASSOC);

// Fetch accepted books requested by the user
$sql = "
    SELECT b.book_id, b.title, b.author, b.image_url, r.status, r.request_id 
    FROM books b
    JOIN requests r ON b.book_id = r.book_id
    WHERE r.requester_id = ? AND r.status = 'Accepted'
    ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$acceptedRequestedBooks = $result->fetch_all(MYSQLI_ASSOC);

// Fetch accepted requests where the user is the owner (books owned by the user)
$sql = "
    SELECT b.book_id, b.title, b.author, b.image_url, r.status, r.request_id, r.requester_id
    FROM books b
    JOIN requests r ON b.book_id = r.book_id
    WHERE b.owner_id = ? AND r.status = 'Accepted'
    ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$ownedAcceptedBooks = $result->fetch_all(MYSQLI_ASSOC);

// Fetch books taken by the user (borrowed books)
$takenBooksQuery = "SELECT books.title, books.author, books.image_url, books.book_id, users.id AS requester_name, r.request_id, r.accepted_at
                    FROM requests r 
                    JOIN books ON r.book_id = books.book_id 
                    JOIN users ON r.requester_id = users.id 
                    WHERE r.requester_id = ? AND r.status = 'Picked Up'";

$stmt = $conn->prepare($takenBooksQuery);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $_SESSION['user_id']);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$takenBooksResult = $stmt->get_result();
$takenBooks = $takenBooksResult->fetch_all(MYSQLI_ASSOC);

// Fetch books given by the user (lent books)
$givenBooksQuery = "SELECT books.title, books.author, books.image_url, books.book_id, users.id AS requester_name, r.request_id 
                    FROM requests r 
                    JOIN books ON r.book_id = books.book_id 
                    JOIN users ON r.requester_id = users.id 
                    WHERE books.owner_id = ? AND r.status = 'Picked Up'";
$stmt = $conn->prepare($givenBooksQuery);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $_SESSION['user_id']);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$givenBooksResult = $stmt->get_result();
$givenBooks = $givenBooksResult->fetch_all(MYSQLI_ASSOC);

// Count pending requests where the user is the owner
$pendingRequestsCount = 0;
$sql = "SELECT COUNT(*) AS count 
        FROM requests 
        WHERE owner_id = ? AND status = 'Pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$pendingRequestsCount = $stmt->get_result()->fetch_assoc()['count'];

// Fetch books owned by the user
$sql = "SELECT * FROM books WHERE owner_id = ? AND book_id NOT IN (SELECT book_id FROM deleted_books)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$latestBooks = $result->fetch_all(MYSQLI_ASSOC);

// Handle book deletion
if (isset($_POST['delete_book'])) {
    $book_id = $_POST['delete_book'];
    if (!empty($book_id)) {
        $sql = "INSERT INTO deleted_books (book_id) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $book_id);
        if ($stmt->execute()) {
            $updateMessage = "✅ Book marked as deleted.";
            header("Location: account.php");
        } else {
            $updateMessage = "❌ Error: {$stmt->error}";
        }
    } else {
        $updateMessage = "❌ Error: Book ID is missing.";
    }
}

// Fetch history for the user (returned requests as owner or borrower)
$sql = "
    SELECT 
        r.request_id, 
        r.book_id, 
        r.requester_id AS borrower_id, 
        b.owner_id, 
        r.created_at AS timestamp, 
        b.title, 
        b.author, 
        b.image_url,
        CONCAT(bu.first_name, ' ', bu.last_name) AS borrower_name,
        CONCAT(ou.first_name, ' ', ou.last_name) AS owner_name
    FROM requests r
    JOIN books b ON r.book_id = b.book_id
    JOIN users bu ON r.requester_id = bu.id
    JOIN users ou ON b.owner_id = ou.id
    WHERE r.status = 'Returned' AND (r.requester_id = ? OR b.owner_id = ?)
    ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$history = $result->fetch_all(MYSQLI_ASSOC);

// Remove request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_request'])) {
    $book_id = intval($_POST['book_id']);
    $stmt = $conn->prepare("DELETE FROM requests WHERE book_id = ? AND requester_id = ?");
    $stmt->bind_param("ii", $book_id, $user_id);
    header("Location: account.php");
    $stmt->execute();
}

$sql = "SELECT * FROM events WHERE user_id = ? ORDER BY event_date ASC, event_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userEvents = $result->fetch_all(MYSQLI_ASSOC);

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $rated_user_id = intval($_POST['rated_user_id']);
    $request_id = intval($_POST['request_id']);
    $rating = intval($_POST['rating']);
    $review = trim($_POST['review']);

    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $updateMessage = "❌ Please select a rating between 1 and 5.";
    } else {
        // Check if rating already exists for this request
        $checkSql = "SELECT id FROM user_ratings WHERE request_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $request_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $updateMessage = "❌ You've already rated this transaction.";
        } else {
            // Insert new rating
            $insertSql = "INSERT INTO user_ratings (rated_user_id, rater_user_id, request_id, rating, review) 
                          VALUES (?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iiiis", $rated_user_id, $user_id, $request_id, $rating, $review);

            if ($insertStmt->execute()) {
                $updateMessage = "✅ Thank you for your rating!";
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'account.php';
                    }, 3000);
                </script>";
            } else {
                $updateMessage = "❌ Error submitting rating: " . $insertStmt->error;
            }
        }
    }
}

// Fetch user's average rating
$avgRating = 0;
$ratingCount = 0;
$userReviews = [];

$ratingSql = "SELECT AVG(rating) as avg_rating, COUNT(*) as count 
              FROM user_ratings 
              WHERE rated_user_id = ?";
$ratingStmt = $conn->prepare($ratingSql);
$ratingStmt->bind_param("i", $user_id);
$ratingStmt->execute();
$ratingResult = $ratingStmt->get_result();
if ($ratingRow = $ratingResult->fetch_assoc()) {
    $avgRating = round($ratingRow['avg_rating'], 1);
    $ratingCount = $ratingRow['count'];
}

// Fetch user's reviews
$reviewSql = "SELECT ur.rating, ur.review, ur.created_at, 
                     CONCAT(u.first_name, ' ', u.last_name) as rater_name,
                     u.profile_pic as rater_image
              FROM user_ratings ur
              JOIN users u ON ur.rater_user_id = u.id
              WHERE ur.rated_user_id = ?
              ORDER BY ur.created_at DESC";
$reviewStmt = $conn->prepare($reviewSql);
$reviewStmt->bind_param("i", $user_id);
$reviewStmt->execute();
$reviewResult = $reviewStmt->get_result();
$userReviews = $reviewResult->fetch_all(MYSQLI_ASSOC);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookLoop - Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="output.css" rel="stylesheet">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 50;
            width: 256px;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
            background-color: white;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .sidebar-open {
            transform: translateX(0);
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
            z-index: 40;
            display: none;
        }

        .main-nav {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 0.5rem;
            background-color: white;
            padding: 0.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid #D1D5DB;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .main-nav-link {
            flex: 0 0 auto;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            color: #4B5563;
            transition: background-color 0.2s;
            border-left: 1px solid #D1D5DB;
            border-top: 1px solid #D1D5DB;
            margin-bottom: -1px;
        }

        .main-nav-link:first-child {
            border-left: none;
        }

        .tab-btn {
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            color: #4B5563;
            transition: background-color 0.2s, color 0.2s;
            white-space: nowrap;
        }

        .tab-btn.tab-active {
            background-color: #2563EB;
            color: white;
            font-weight: 600;
            border-bottom: 2px solid #74C0FC;
        }

        @media (max-width: 767px) {
            body {
                flex-direction: column;
            }

            .main-nav {
                flex-direction: row;
                overflow-x: auto;
                padding: 0.5rem;
                gap: 0.25rem;
            }

            .tab-btn {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }

            .section .grid {
                grid-template-columns: 1fr !important;
            }

            .section img {
                width: 8rem;
                height: 12rem;
            }

            .section .text-center p,
            .section .text-center a,
            .section .text-center button {
                font-size: 0.875rem;
            }

            .edit-profile-form {
                width: 100%;
            }

            .edit-profile-form input,
            .edit-profile-form button {
                font-size: 0.875rem;
                padding: 0.5rem;
            }
        }

        @media (min-width: 1024px) {
            .sidebar {
                position: static;
                transform: translateX(0);
                flex-shrink: 0;
                top: auto;
                left: auto;
                bottom: auto;
                z-index: auto;
            }

            .sidebar-overlay {
                display: none;
            }

            body {
                flex-direction: row;
            }

            /* Rating & Review Styles */
.rating-stars {
    display: inline-block;
    font-size: 0;
}

.rating-stars input {
    display: none;
}

.rating-stars label {
    font-size: 24px;
    color: #ddd;
    cursor: pointer;
    display: inline-block;
    margin-right: 5px;
    transition: all 0.2s ease;
}

.rating-stars input:checked ~ label,
.rating-stars label:hover,
.rating-stars label:hover ~ label {
    color: #FFD700;
}

.rating-stars input:checked + label {
    color: #FFD700;
}

.review-card {
    border-left: 4px solid #2563EB;
    background-color: #F8FAFC;
    padding: 1.5rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.star-filled {
    color: #FFD700;
}

.star-empty {
    color: #E5E7EB;
}

/* Transparent Modal Styles */
#ratingModal {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    background-color: rgba(255, 255, 255, 0.85); /* Semi-transparent white */
    backdrop-filter: blur(4px); /* Blur effect */
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

#ratingModal > div {
    background: white;
    padding: 2rem;
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    width: 90%;
    max-width: 32rem;
    border: 1px solid #e5e7eb;
    transform: translateY(0);
    transition: transform 0.3s ease;
}

/* Star Rating in Modal */
.modal-rating-stars {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin: 1.5rem 0;
}

.modal-rating-stars label {
    font-size: 2rem;
    color: #e5e7eb;
    cursor: pointer;
    transition: all 0.2s ease;
}

.modal-rating-stars label:hover,
.modal-rating-stars input:checked ~ label {
    color: #FFD700;
    transform: scale(1.1);
}

/* Review Textarea */
.review-textarea {
    width: 100%;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    min-height: 6rem;
    margin-bottom: 1.5rem;
    transition: border-color 0.2s ease;
}

.review-textarea:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Modal Buttons */
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.modal-btn {
    padding: 0.5rem 1.25rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.modal-btn-cancel {
    background-color: #f3f4f6;
    color: #4b5563;
}

.modal-btn-cancel:hover {
    background-color: #e5e7eb;
}

.modal-btn-submit {
    background-color: #2563EB;
    color: white;
}

.modal-btn-submit:hover {
    background-color: #1d4ed8;
}

/* Responsive Adjustments */
@media (max-width: 640px) {
    #ratingModal > div {
        padding: 1.5rem;
    }
    
    .modal-rating-stars label {
        font-size: 1.75rem;
    }
}
        }
    </style>
</head>
<body class="bg-gray-100 font-[Poppins] flex flex-col lg:flex-row">
<header class="lg:hidden flex flex-col items-center justify-between bg-gradient-to-r from-purple-800 to-blue-900 text-white rounded-b-xl p-4 shadow-md w-full">
    <div class="flex items-center justify-between w-full">
        <div class="flex items-center gap-2">
            <button id="hamburgerBtn" class="text-white text-2xl focus:outline-none">
                <i class="fas fa-bars" style="color: #74C0FC;"></i>
            </button>
            <div>
                <h1 class="text-xl font-bold leading-snug text-blue-600">Keep books moving,</h1>
                <p class="text-base font-light -mt-1 text-blue-600">Keep stories alive.</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <a href="notifications.php">
                <span class="text-2xl cursor-pointer flex items-center relative">
                    <i class="fa-solid fa-bell" style="color: #74C0FC;"></i>
                    <?php if ($pendingRequestsCount > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?= $pendingRequestsCount ?>
                        </span>
                    <?php endif; ?>
                </span>
            </a>
            <a href="account.php">
                <img src="<?= htmlspecialchars($user['profile_pic'] ?: './assets/055a91979264664a1ee12b9453610d82.jpg') ?>?t=<?= time() ?>" alt="User" class="w-10 h-10 rounded-full border-2 border-white cursor-pointer object-cover">
            </a>
        </div>
    </div>
    <h1 class="text-lg font-semibold text-blue-600 mt-2">Hi, <?= htmlspecialchars($user['first_name']) ?>!</h1>
</header>

<aside id="sidebar" class="sidebar w-64 bg-white shadow-lg p-6">
        <button id="closeSidebarBtn" class="absolute top-4 right-4 text-gray-600 text-2xl focus:outline-none lg:hidden">
            <i class="fas fa-times"></i>
        </button>

        <h2 class="mb-8 flex items-center justify-center">
            <img src="./assets/landing.jpeg" alt="Book Icon" class="w-40 h-40 object-cover rounded-full">
        </h2>

        <nav>
            <ul>
                <li><a href="home.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'home.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-house mr-3" style="color: #74C0FC;"></i>Home</a></li>
                <li><a href="books.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'books.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-book mr-3" style="color: #74C0FC;"></i> Books</a></li>
                <li><a href="listbooks.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'listbooks.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-plus mr-3" style="color: #74C0FC;"></i> List Books</a></li>
                <li>
                    <a href="requests.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'requests.php' ? 'bg-blue-100' : '' ?>">
                        <i class="fa-solid fa-envelope mr-3" style="color: #74C0FC;"></i> Requests
                    </a>
                </li>
                <li>
                    <a href="message.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 relative <?= $current_page === 'message.php' ? 'bg-blue-100' : '' ?>">
                        <i class="fa-solid fa-message mr-2" style="color: #74C0FC;"></i> Message
                        <?php if (($unreadCount ?? 0) > 0): ?>
                            <span class="absolute top-2 left-6 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?= $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="notifications.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'notifications.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-bell mr-3" style="color: #74C0FC;"></i> Notifications</a></li>
                <li><a href="events.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'events.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-calendar-days mr-3" style="color: #74C0FC;"></i> Events</a></li>
                <li><a href="posts.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'posts.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-image mr-3" style="color: #74C0FC;"></i> Posts</a></li>
                <li><a href="support.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'support.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-ticket mr-3" style="color: #74C0FC;"></i> Support</a></li>
                <li><a href="account.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'account.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-user mr-3" style="color: #74C0FC;"></i> Profile</a></li>
                <li><a href="logout.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'logout.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-right-from-bracket mr-3" style="color: #74C0FC;"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <div id="sidebarOverlay" class="sidebar-overlay hidden"></div>

    <main class="flex-1 p-6 overflow-x-hidden">
        <header class="hidden lg:flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold text-blue-900">Hi, <?= htmlspecialchars($user['first_name']) ?>!</h1>
            <div class="flex items-center gap-4">
                <a href="notifications.php">
                    <span class="text-2xl cursor-pointer flex items-center relative">
                        <i class="fa-solid fa-bell" style="color: #74C0FC;"></i>
                        <?php if ($pendingRequestsCount > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?= $pendingRequestsCount ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </a>
                <a href="account.php">
                    <img src="<?= htmlspecialchars($user['profile_pic'] ?: './assets/055a91979264664a1ee12b9453610d82.jpg') ?>?t=<?= time() ?>" alt="User" class="w-10 h-10 rounded-full border-2 border-blue-900 cursor-pointer object-cover">
                </a>
            </div>
        </header>

        <nav class="main-nav">
    <a href="#my-books" data-section="my-books" class="tab-btn">📚 My Books</a>
    <a href="#requested-books" data-section="requested-books" class="tab-btn">📨 Requested Books</a>
    <a href="#exchange" data-section="exchange" class="tab-btn">🔁 Exchange</a>
    <a href="#borrows" data-section="borrows" class="tab-btn">📗 Borrowed Books</a>
    <a href="#events" data-section="events" class="tab-btn">🎈 My Events</a>
    <a href="#history" data-section="history" class="tab-btn">📖 History</a>
 
    <a href="#edit-profile" data-section="edit-profile" class="tab-btn">✏ Edit Profile</a>
</nav>

        <?php if (!empty($updateMessage)): ?>
            <div class="p-4 mb-4 bg-green-100 text-green-700 rounded-lg"><?= $updateMessage ?></div>
        <?php endif; ?>

        <div id="my-books" class="section bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-xl font-bold mb-8">My Added Books</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php if (!empty($latestBooks)): ?>
                    <?php foreach ($latestBooks as $book): ?>
                        <div class="relative group">
                            <a href="account.php?title=<?= urlencode($book['title']) ?>" class="text-center cursor-pointer flex flex-col items-center justify-center">
                                <img src="<?= htmlspecialchars($book['image_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="w-48 h-64 object-cover rounded-lg">
                                <p class="font-bold mt-2 text-center"><?= htmlspecialchars($book['title']) ?></p>
                            </a>
                            <form method="POST" class="absolute top-2 right-2">
                                <input type="hidden" name="delete_book" value="<?= $book['book_id'] ?>">
                                <button 
                                    type="submit" 
                                    onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($book['title'])) ?>?')"
                                    class="bg-red-500 text-white p-2 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                    title="Delete Book">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500">No books added yet. Start adding now!</p>
                <?php endif; ?>
            </div>
        </div>

        <div id="requested-books" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-xl font-bold mb-8">Requested Books</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php if (!empty($pendingRequestedBooks)): ?>
                    <?php foreach ($pendingRequestedBooks as $book): ?>
                        <div class="text-center cursor-pointer flex flex-col items-center justify-center bg-gray-50 p-4 rounded-lg">
                            <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                 alt="<?= htmlspecialchars($book['title']) ?>"
                                 class="w-48 h-64 object-cover rounded-lg">
                            <p class="font-bold mt-2"><?= htmlspecialchars($book['title']) ?></p>
                            <p class="text-gray-500"><?= htmlspecialchars($book['author']) ?></p>
                            <p class="text-sm font-semibold text-yellow-500">
                                <?= htmlspecialchars($book['status']) ?>
                            </p>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove this request?');">
                                <input type="hidden" name="book_id" value="<?= htmlspecialchars($book['book_id']) ?>">
                                <input type="hidden" name="remove_request" value="1">
                                <button type="submit" class="mt-2 px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm">
                                    Remove Request
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500">No requested books yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($acceptedRequestedBooks) || !empty($ownedAcceptedBooks)): ?>
            <div id="exchange" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
                <?php if (!empty($acceptedRequestedBooks)): ?>
                    <h4 class="text-lg font-semibold text-black mb-4">Books Requested by Me</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">
                        <?php foreach ($acceptedRequestedBooks as $book): ?>
                            <div class="bg-gray-50 p-4 rounded-lg shadow text-center">
                                <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                     alt="<?= htmlspecialchars($book['title']) ?>"
                                     class="w-48 h-64 object-cover rounded-lg border-2 border-green-500 mx-auto">
                                <p class="font-bold mt-2"><?= htmlspecialchars($book['title']) ?></p>
                                <p class="text-gray-500"><?= htmlspecialchars($book['author']) ?></p>
                                <p class="text-sm font-semibold text-green-600 mb-2"> (<?= htmlspecialchars($book['status']) ?> - Proceed to exchange)</p>
                                <a href="view_otp.php?request_id=<?= urlencode($book['request_id']) ?>" 
                                   class="inline-block bg-blue-900 text-white px-4 py-1 mt-2 rounded-full text-sm hover:bg-blue-200">
                                   Get Pickup OTP
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($ownedAcceptedBooks)): ?>
                    <h4 class="text-lg font-semibold text-black mb-4">Books Owned by Me (Accepted Requests)</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <?php foreach ($ownedAcceptedBooks as $book): ?>
                            <div class="bg-gray-50 p-4 rounded-lg shadow text-center">
                                <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                     alt="<?= htmlspecialchars($book['title']) ?>"
                                     class="w-48 h-64 object-cover rounded-lg border-2 border-green-500 mx-auto">
                                <p class="font-bold mt-2"><?= htmlspecialchars($book['title']) ?></p>
                                <p class="text-gray-500"><?= htmlspecialchars($book['author']) ?></p>
                                <p class="text-sm font-semibold text-green-600 mb-2"> (<?= htmlspecialchars($book['status']) ?> - Proceed to exchange)</p>
                                <a href="verify_otp.php?request_id=<?= urlencode($book['request_id']) ?>" 
                                   class="inline-block bg-green-100 text-green-700 px-4 py-1 mt-2 rounded-full text-sm hover:bg-green-200">
                                   Verify Pickup OTP
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div id="exchange" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
                <h4 class="text-lg font-semibold text-black mb-4">Books in Exchange</h4>
                <p class="text-center text-lg font-semibold text-gray-600">No books in exchange to show.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($takenBooks) || !empty($givenBooks)): ?>
            <div id="borrows" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
                <h3 class="text-xl font-bold text-black mb-6">Borrowed Books</h3>
                <?php if (!empty($takenBooks)): ?>
                    <h4 class="text-lg font-semibold text-black mb-4">Books Taken by Me</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">
                      <?php foreach ($takenBooks as $book): ?>
    <?php 
        $acceptedAt = strtotime($book['accepted_at']);
        $deadline = date('F j, Y', strtotime('+14 days', $acceptedAt)); 
    ?>
    <div class="bg-gray-50 p-4 rounded-lg shadow text-center">
        <img src="<?= htmlspecialchars($book['image_url']) ?>"
             alt="<?= htmlspecialchars($book['title']) ?>"
             class="w-48 h-64 object-cover rounded-lg border-2 border-indigo-500 mx-auto">
        <p class="font-bold mt-2"><?= htmlspecialchars($book['title']) ?></p>
        <p class="text-gray-500"><?= htmlspecialchars($book['author']) ?></p>
        <p class="text-sm text-red-600 font-medium mt-1">Return by: <?= $deadline ?></p>
        <p class="text-sm font-semibold text-indigo-600 mt-2">✔ Picked Up</p>
        <a href="verify_return_otp.php?request_id=<?= urlencode($book['request_id']) ?>" 
           class="inline-block bg-blue-900 text-white px-4 py-1 mt-2 rounded-full text-sm hover:bg-blue-200">
           Return Book
        </a>
    </div>
<?php endforeach; ?>


                    </div>
                <?php endif; ?>
                <?php if (!empty($givenBooks)): ?>
                    <h4 class="text-lg font-semibold text-black mb-4">Books Given by Me</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <?php foreach ($givenBooks as $book): ?>
                            <div class="bg-gray-50 p-4 rounded-lg shadow text-center">
                                <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                     alt="<?= htmlspecialchars($book['title']) ?>"
                                     class="w-48 h-64 object-cover rounded-lg border-2 border-indigo-500 mx-auto">
                                <p class="font-bold mt-2"><?= htmlspecialchars($book['title']) ?></p>
                                <p class="text-gray-500"><?= htmlspecialchars($book['author']) ?></p>
                                <p class="text-sm font-semibold text-indigo-600 mt-2">✔ Picked Up</p>
                                <a href="view_return_otp.php?request_id=<?= urlencode($book['request_id']) ?>" 
                                   class="inline-block bg-green-100 text-blue-700 px-4 py-1 mt-2 rounded-full text-sm hover:bg-blue-200">
                                   View Return OTP
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div id="borrows" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
                <h3 class="text-xl font-bold text-black mb-6">Borrowed Books</h3>
                <p class="text-center text-lg font-semibold text-gray-600">No borrowed books to show.</p>
            </div>
        <?php endif; ?>

<div id="events" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
    <h3 class="text-xl font-bold mb-8">My Events</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php if (!empty($userEvents)): ?>
            <?php foreach ($userEvents as $event): ?>
                <div class="relative group">
                    <a href="events.php" class="text-center cursor-pointer flex flex-col items-center justify-center">
                        <?php if (!empty($event['image_url'])): ?>
                            <img src="<?= htmlspecialchars($event['image_url']) ?>" alt="<?= htmlspecialchars($event['event_name']) ?>" class="w-48 h-64 object-cover rounded-lg">
                        <?php else: ?>
                            <div class="w-48 h-64 bg-gray-200 flex items-center justify-center rounded-lg">
                                <i class="fas fa-calendar-day text-4xl text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                        <p class="font-bold mt-2 text-center"><?= htmlspecialchars($event['event_name']) ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?= date('F j, Y', strtotime($event['event_date'])) ?> at <?= date('g:i A', strtotime($event['event_time'])) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($event['venue']) ?></p>
                        <p class="text-sm font-semibold mt-1 <?php
                            echo $event['status'] === 'Pending' ? 'text-yellow-500' :
                                 ($event['status'] === 'Approved' ? 'text-green-600' : 'text-red-500');
                        ?>">
                            <?= htmlspecialchars($event['status']) ?>
                        </p>
                    </a>
                    
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-500">No events added yet. Start creating now!</p>
        <?php endif; ?>
    </div>
</div>

<div id="history" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
    <h3 class="text-xl font-bold mb-8">History</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php if (!empty($history)): ?>
            <?php foreach ($history as $entry): ?>
                <div class="bg-gray-50 p-4 rounded-lg shadow text-center relative">
                    <img src="<?= htmlspecialchars($entry['image_url']) ?>"
                         alt="<?= htmlspecialchars($entry['title']) ?>"
                         class="w-48 h-64 object-cover rounded-lg mx-auto">
                    <p class="font-bold mt-2"><?= htmlspecialchars($entry['title']) ?></p>
                    <p class="text-gray-500"><?= htmlspecialchars($entry['author']) ?></p>
                    <p class="text-sm font-semibold text-green-600 mt-2">
                        Returned on <?= date('F j, Y, g:i a', strtotime($entry['timestamp'])) ?>
                    </p>
                    <p class="text-sm text-gray-600">
                        <?= $entry['owner_id'] == $user_id ? 'Lent to ' . htmlspecialchars($entry['borrower_name']) : 'Borrowed from ' . htmlspecialchars($entry['owner_name']) ?>
                    </p>
                    
                    <?php 
                    // Check if current user is the owner and can rate the borrower
                    if ($entry['owner_id'] == $user_id): 
                        // Check if rating already exists for this request
                        $checkRatingSql = "SELECT id FROM user_ratings WHERE request_id = ?";
                        $checkRatingStmt = $conn->prepare($checkRatingSql);
                        $checkRatingStmt->bind_param("i", $entry['request_id']);
                        $checkRatingStmt->execute();
                        $ratingExists = $checkRatingStmt->get_result()->num_rows > 0;
                    ?>
                        <button onclick="showRatingModal(<?= $entry['request_id'] ?>, <?= $entry['borrower_id'] ?>, '<?= addslashes($entry['title']) ?>')"
        class="mt-2 px-3 py-1 bg-blue-900 text-white rounded hover:bg-blue-700 text-sm <?= $ratingExists ? 'hidden' : '' ?>"
        style="cursor: pointer; z-index: 10;">
    Rate Borrower
</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-600">No returned books yet.</p>
        <?php endif; ?>
    </div>
</div>



        <div id="edit-profile" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-xl font-bold mb-8">Edit Profile</h3>
            <form method="POST" enctype="multipart/form-data" class="edit-profile-form max-w-md mx-auto">
                <div class="mb-4">
                    <label for="first_name" class="block text-gray-700 font-medium mb-2">First Name</label>
                    <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900" required>
                </div>
                <div class="mb-4">
                    <label for="last_name" class="block text-gray-700 font-medium mb-2">Last Name</label>
                    <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-gray-700 font-medium mb-2">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" id="password" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900">
                </div>
                <div class="mb-4">
                    <label for="profile_picture" class="block text-gray-700 font-medium mb-2">Profile Picture</label>
                    <input type="file" name="profile_picture" id="profile_picture" accept="image/*" 
                           class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
                <button type="submit" name="update_profile" 
                        class="w-full bg-blue-900 text-white p-3 rounded-lg hover:bg-blue-800 transition duration-300">
                    Update Profile
                </button>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');

    function openSidebar() {
        sidebar.classList.add('sidebar-open');
        sidebarOverlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('sidebar-open');
        sidebarOverlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    if (hamburgerBtn && sidebar && sidebarOverlay && closeSidebarBtn) {
        hamburgerBtn.addEventListener('click', openSidebar);
        closeSidebarBtn.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        // Close sidebar when clicking on navigation links
        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', closeSidebar);
        });
    }

    // Tab Navigation
    const tabButtons = document.querySelectorAll('.tab-btn');
    const sections = document.querySelectorAll('.section');

    function showSection(sectionId) {
        sections.forEach(section => {
            section.classList.add('hidden');
            if (section.id === sectionId) {
                section.classList.remove('hidden');
            }
        });

        tabButtons.forEach(btn => {
            btn.classList.remove('tab-active');
            if (btn.getAttribute('data-section') === sectionId) {
                btn.classList.add('tab-active');
            }
        });
    }

    tabButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            const targetId = this.getAttribute('data-section');
            showSection(targetId);
            
            // Update URL hash
            window.location.hash = targetId;
        });
    });

    // Show section based on URL hash
    if (window.location.hash) {
        const targetId = window.location.hash.substring(1);
        showSection(targetId);
    } else if (tabButtons.length > 0) {
        // Default to first tab
        const defaultTab = tabButtons[0].getAttribute('data-section');
        showSection(defaultTab);
    }
});

// Rating Modal Functions (global)
function showRatingModal(requestId, ratedUserId, bookTitle) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 flex items-center justify-center z-50';
    modal.style.backgroundColor = 'rgba(255, 255, 255, 0.8)'; // Semi-transparent white
    modal.style.backdropFilter = 'blur(2px)'; // Optional blur effect
    modal.id = 'ratingModal';
    
    // Rest of your modal content creation code remains the same...
    modal.innerHTML = `
     <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4 shadow-xl border border-gray-200">
            <h3 class="text-xl font-bold mb-4">Rate this transaction</h3>
            <p class="mb-4">How was your experience with this book exchange for "${bookTitle}"?</p>
            
            <form id="ratingForm" method="POST" class="space-y-4">
                <input type="hidden" name="request_id" value="${requestId}">
                <input type="hidden" name="rated_user_id" value="${ratedUserId}">
                
                <div class="rating-stars flex justify-center space-x-2 mb-4">
                    ${[1, 2, 3, 4, 5].map(i => `
                        <input type="radio" id="star${i}" name="rating" value="${i}" class="hidden">
                        <label for="star${i}" class="text-3xl cursor-pointer star" 
                               data-value="${i}">☆</label>
                    `).join('')}
                </div>
                
                <textarea name="review" placeholder="Optional: Share your experience..." 
                          class="w-full p-2 border rounded" rows="3"></textarea>
                
                <div class="flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeRatingModal()" 
                            class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400 transition">Cancel</button>
                    <button type="submit" name="submit_rating" 
                            class="px-4 py-2 bg-blue-900 text-white rounded hover:bg-blue-800 transition">Submit</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    // Add star rating interaction
    const stars = modal.querySelectorAll('.star');
    let currentRating = 0;
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            currentRating = parseInt(this.getAttribute('data-value'));
            updateStars(currentRating);
        });
        
        star.addEventListener('mouseover', function() {
            const hoverRating = parseInt(this.getAttribute('data-value'));
            updateStars(hoverRating);
        });
        
        star.addEventListener('mouseout', function() {
            updateStars(currentRating);
        });
    });
    
    // Form submission handler
    modal.querySelector('form').addEventListener('submit', function(e) {
        if (!this.querySelector('input[name="rating"]:checked')) {
            e.preventDefault();
            alert('Please select a rating');
        }
    });
    
    // Prevent background scrolling when modal is open
    document.body.style.overflow = 'hidden';
}

function updateStars(rating) {
    const modal = document.getElementById('ratingModal');
    if (!modal) return;
    
    const stars = modal.querySelectorAll('.star');
    stars.forEach((star, index) => {
        const starValue = parseInt(star.getAttribute('data-value'));
        star.textContent = starValue <= rating ? '★' : '☆';
        star.style.color = starValue <= rating ? '#FFD700' : '#ddd';
        
        // Update the corresponding radio input
        if (starValue === rating) {
            modal.querySelector(`#star${rating}`).checked = true;
        }
    });
}

function closeRatingModal() {
    const modal = document.getElementById('ratingModal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = '';
    }
}

// Close modal when clicking outside content
document.addEventListener('click', function(event) {
    const modal = document.getElementById('ratingModal');
    if (modal && event.target === modal) {
        closeRatingModal();
    }
});
    </script>
</body>
</html>
<?php
$conn->close();
?>