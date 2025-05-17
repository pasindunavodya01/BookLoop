<?php
session_start();
include 'db.php'; // Include the database connection file

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Ensure get_unread_messages.php exists and works correctly
require_once 'get_unread_messages.php';
$unreadCount = getUnreadMessagesCount($conn, $_SESSION['user_id']);

$pendingRequestsCount = 0;
$sql = "SELECT COUNT(*) AS count
        FROM requests
        WHERE owner_id = ? AND status = 'Pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$pendingRequestsCount = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Fetch current user's profile picture and first name
$user_id = $_SESSION['user_id'];
$sql = "SELECT profile_pic, first_name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_profile_pic = !empty($user['profile_pic']) ? $user['profile_pic'] : './assets/055a91979264664a1ee12b9453610d82.jpg';
$user_first_name = htmlspecialchars($user['first_name'] ?? 'User');

// Fetch the latest posts along with the number of likes and comments
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : ''; // Trim whitespace
$postsQuery = "
    SELECT p.post_id, p.content, p.image_url, p.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS username,
           u.profile_pic,
           (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) AS likes_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) AS comments_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.content LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?
    ORDER BY p.created_at DESC
";
$stmt = $conn->prepare($postsQuery);
$searchParam = "%$searchQuery%";
$stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
$stmt->execute();
$postsResult = $stmt->get_result();


// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $user_id = $_SESSION['user_id'];
    $content = trim($_POST['content']);

    $image_url = NULL;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (in_array($_FILES['image']['type'], $allowed_types) && $_FILES['image']['size'] <= $max_size) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_file_name = uniqid() . '.' . $file_ext;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_url = $target_file;
            } else {
                error_log("Failed to move uploaded file.");
            }
        } else {
            error_log("Invalid file type or size.");
        }
    }

    if (!empty($content) || !empty($image_url)) {
        $insertPostQuery = "INSERT INTO posts (user_id, content, image_url) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertPostQuery);
        $stmt->bind_param("iss", $user_id, $content, $image_url);
        if ($stmt->execute()) {
            $redirectUrl = 'posts.php';
            if (!empty($searchQuery)) {
                $redirectUrl .= '?search=' . urlencode($searchQuery);
            }
            header("Location: " . $redirectUrl);
            exit();
        } else {
            error_log("Database error inserting post: " . $stmt->error);
        }
    }
    $redirectUrl = 'posts.php';
    if (!empty($searchQuery)) {
        $redirectUrl .= '?search=' . urlencode($searchQuery);
    }
    header("Location: " . $redirectUrl);
    exit();
}


// Handle like functionality
if (isset($_GET['like_post_id'])) {
    $post_id = $_GET['like_post_id'];
    $user_id = $_SESSION['user_id'];

    $checkLikeQuery = "SELECT * FROM likes WHERE post_id = ? AND user_id = ?";
    $stmt = $conn->prepare($checkLikeQuery);
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $likeResult = $stmt->get_result();

    if ($likeResult->num_rows == 0) {
        $likeQuery = "INSERT INTO likes (post_id, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($likeQuery);
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
    }
    $redirectUrl = 'posts.php';
    if (!empty($searchQuery)) {
        $redirectUrl .= '?search=' . urlencode($searchQuery);
    }
    header("Location: " . $redirectUrl);
    exit();
}

// Handle comment functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_content'], $_POST['post_id'])) {
    $user_id = $_SESSION['user_id'];
    $content = trim($_POST['comment_content']);
    $post_id = $_POST['post_id'];

    if (!empty($content)) {
        $insertCommentQuery = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
        $insertCommentQuery = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertCommentQuery);
        $stmt->bind_param("iis", $post_id, $user_id, $content);
        if ($stmt->execute()) {
            $redirectUrl = 'posts.php';
            if (!empty($searchQuery)) {
                $redirectUrl .= '?search=' . urlencode($searchQuery);
            }
            header("Location: " . $redirectUrl);
            exit();
        } else {
            error_log("Database error inserting comment: " . $stmt->error);
        }
    }
    $redirectUrl = 'posts.php';
    if (!empty($searchQuery)) {
        $redirectUrl .= '?search=' . urlencode($searchQuery);
    }
    header("Location: " . $redirectUrl);
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookLoop - Posts</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="output.css" rel="stylesheet">
    <style>
        /* Custom styles for sidebar transition and mobile positioning */
        .sidebar {
            /* Mobile Default State (Hidden) */
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 50; /* Ensure it's above other content */
            width: 256px; /* Match Tailwind w-64 */
            transform: translateX(-100%); /* Initially hide off-screen */
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
            background-color: white; /* Ensure background on mobile */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* Add shadow */
        }

        /* Adjust sidebar top position to be below the mobile header */
        @media (max-width: 1023px) { /* Below lg breakpoint */
            .sidebar {
                top: 64px; /* Assuming mobile header height is around 64px (p-4 flex items-center = ~4rem) */
            }
        }


        .sidebar-open {
            transform: translateX(0); /* Slide in when open */
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0; /* Top, right, bottom, left to 0 */
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
            z-index: 40; /* Below sidebar, above main content */
            display: none; /* Hidden by default */
        }

        /* Desktop styles */
        @media (min-width: 1024px) { /* Tailwind's lg breakpoint is 1024px */
            .sidebar {
                /* Desktop State (Visible and part of flex layout) */
                position: static; /* Revert to static for flex layout */
                transform: translateX(0) !important; /* Always visible, override JS */
                flex-shrink: 0; /* Prevent sidebar from shrinking */
                /* Remove fixed positioning related styles */
                top: auto;
                left: auto;
                bottom: auto;
                z-index: auto; /* Reset z-index */
            }

            .sidebar-overlay { /* Hide overlay on desktop */
                display: none !important;
            }

            /* Ensure body is a flex container on desktop */
            body.lg\:flex-row {
                flex-direction: row;
            }

            /* Ensure desktop header uses flex */
             header.lg\:flex { /* Keep this rule as it was in your original code */
                 display: flex;
             }
        }

        .post-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .post-container > div:first-child {
            flex: 1;
        }

        .comments-wrapper {
            flex: 1;
            width: 100%; /* Default to full width on small screens */
        }

        .comments-container {
            height: auto; /* Default to auto height on small screens */
        }

        /* Hide comments after the second one by default on smaller screens */
        .comment-list > div:nth-child(n+3) {
            display: none;
        }

        .comment-list.show-all > div {
            display: flex !important; /* Ensure flex display when showing all */
        }

        .see-more-comments {
            display: block; /* Block element for centering */
            margin: 0 auto;
            padding: 0.5rem 1rem;
            background-color: #3b82f6;
            color: white;
            border-radius: 0.375rem;
            cursor: pointer;
            text-align: center;
            font-size: 0.875rem;
            border: none; /* Remove default button border */
        }

        .see-more-comments:hover {
            background-color: #2563eb;
        }

        @media (min-width: 768px) { /* md breakpoint */
            .post-container {
                flex-direction: row; /* Side-by-side on larger screens */
                align-items: flex-start;
                gap: 1.5rem;
            }

            .comments-wrapper {
                flex: none; /* Prevent wrapper from growing */
                width: 20rem; /* Fixed width on larger screens */
            }

            .comments-container {
                height: 24rem; /* Fixed height on larger screens */
                overflow-y: auto; /* Ensure scrolling if comments exceed height */
            }

            /* Always show all comments on larger screens */
            .comment-list > div {
                display: flex !important;
            }

            .see-more-comments {
                display: none; /* Hide button on larger screens */
            }
        }

        /* Center images */
        .post-image-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .post-image {
            max-width: 100%;
            height: auto;
            max-height: 20rem;
            object-fit: contain;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-[Poppins] flex flex-col lg:flex-row">
    <header class="fixed top-0 left-0 w-full bg-gradient-to-r from-purple-800 to-blue-900 text-white shadow-md p-4 flex items-center justify-between lg:hidden z-40">
        <div class="flex items-center gap-2">
            <button id="hamburgerBtn" class="text-white text-2xl focus:outline-none">
                <i class="fas fa-bars" style="color: #74C0FC;"></i>
            </button>
            <div class="text-white font-bold text-xl">BookLoop</div>
        </div>

        <div class="flex items-center gap-4">
            <h1 class="text-lg font-semibold text-blue-600"></h1>
            <div class="flex items-center gap-4">
                <a href="notifications.php">
                    <span class="text-2xl cursor-pointer flex items-center relative">
                        <i class="fa-solid fa-bell" style="color: #74C0FC;"></i>
                        <?php if (($pendingRequestsCount ?? 0) > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?= $pendingRequestsCount ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </a>
                <a href="account.php">
                    <img src="<?= htmlspecialchars($user_profile_pic) ?>?t=<?= time() ?>" alt="User" class="w-10 h-10 rounded-full border-2 border-white cursor-pointer object-cover">
                </a>
            </div>
        </div>
    </header>

    <div class="h-16 lg:hidden"></div>

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

    <main class="flex-1 p-4 sm:p-6 overflow-x-hidden">
        <header class="hidden lg:flex items-center justify-between mb-6 bg-gradient-to-r from-purple-100 to-blue-900 text-white rounded-xl p-6 shadow-md">
            <div class="flex items-center gap-4">
                <div class="mr-4">
                    <h1 class="text-xl lg:text-2xl font-bold leading-snug text-blue-900">Keep books moving,</h1>
                    <p class="text-base font-light -mt-1 text-blue-600">Keep stories alive.</p>
                </div>
                <form action="posts.php" method="GET" class="flex items-center gap-4">
                    <input
                        type="text"
                        name="search"
                        class="p-3 rounded-full border border-gray-300 w-64 lg:w-96 text-gray-800"
                        placeholder="Search posts..."
                        value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
                    >
                    <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                 <button id="createPostButton" class="px-6 py-3 bg-blue-900 text-white rounded-lg">
                    Create Post
                </button>
            </div>
            <div class="flex items-center gap-4">
                <a href="notifications.php">
                    <span class="text-2xl cursor-pointer flex items-center relative">
                        <i class="fa-solid fa-bell" style="color: #74C0FC;"></i>
                        <?php if (($pendingRequestsCount ?? 0) > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?= $pendingRequestsCount ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </a>
                <a href="account.php">
                    <img src="<?= htmlspecialchars($user_profile_pic) ?>?t=<?= time() ?>" alt="User" class="w-10 h-10 rounded-full border-2 border-white cursor-pointer object-cover">
                </a>
            </div>
        </header>

        <div class="lg:hidden flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
            <form action="posts.php" method="GET" class="flex items-center gap-2 w-full sm:w-auto">
                <input
                    type="text"
                    name="search"
                    class="p-3 rounded-full border border-gray-300 w-full sm:w-64 text-gray-800"
                    placeholder="Search posts..."
                    value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
                >
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg">
                    <i class="fas fa-search"></i>
                    <span>Search</span> </button>
            </form>
             <button id="createPostButtonMobile" class="px-6 py-3 bg-blue-900 text-white rounded-lg w-full sm:w-auto">
                Create Post
            </button>
        </div>


        <div id="createPostForm" class="bg-white p-4 sm:p-6 rounded-lg shadow-md mb-6 hidden">
            <h2 class="text-xl sm:text-2xl font-bold mb-4">Create a Post</h2>
            <form action="posts.php?search=<?= urlencode($searchQuery) ?>" method="POST" enctype="multipart/form-data">
                <textarea name="content" class="w-full p-3 mb-4 rounded-lg border" placeholder="What's on your mind?" required></textarea>
                <input type="file" name="image" class="mb-4 w-full" accept="image/jpeg,image/png,image/gif">
                <button type="submit" class="px-6 py-3 bg-blue-900 text-white rounded-lg w-full sm:w-auto">Post</button>
            </form>
        </div>

        <div class="p-0 sm:p-6">
            <h2 class="text-xl sm:text-2xl font-bold mb-4">Latest Posts</h2>
            <?php if ($postsResult && $postsResult->num_rows > 0): ?>
                <?php while ($post = $postsResult->fetch_assoc()): ?>
                    <div class="post-container bg-white p-4 sm:p-6 mb-6 rounded-lg shadow-md">
                        <div>
                            <div class="flex items-center mb-4">
                                <img src="<?= htmlspecialchars($post['profile_pic'] ?? './assets/055a91979264664a1ee12b9453610d82.jpg') ?>" alt="Profile" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full mr-3 object-cover">
                                <div>
                                    <p class="font-bold text-sm sm:text-base"><?= htmlspecialchars($post['username'] ?? 'Unknown User') ?></p>
                                    <p class="text-gray-500 text-xs sm:text-sm"><?= date('F j, Y, g:i a', strtotime($post['created_at'] ?? 'now')) ?></p>
                                </div>
                            </div>
                            <p class="mb-4 text-sm sm:text-base"><?= htmlspecialchars($post['content'] ?? '') ?></p>
                            <?php if (!empty($post['image_url'])): ?>
                                <div class="post-image-wrapper mb-4">
                                    <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="Post Image" class="post-image">
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="comments-wrapper">
                            <div class="mb-4 flex items-center gap-4 text-sm sm:text-base">
                                <a href="posts.php?like_post_id=<?= $post['post_id'] ?>&search=<?= urlencode($searchQuery) ?>" class="flex items-center">
                                    ❤️ <span class="ml-1"><?= $post['likes_count'] ?? 0 ?> Likes</span>
                                </a>
                                <a href="#comments-<?= $post['post_id'] ?>" class="flex items-center">
                                    💬 <span class="ml-1"><?= $post['comments_count'] ?? 0 ?> Comments</span>
                                </a>
                            </div>
                            <div id="comments-<?= $post['post_id'] ?>" class="comments-container overflow-y-auto">
                                <form action="posts.php?search=<?= urlencode($searchQuery) ?>" method="POST" class="mb-4">
                                    <textarea name="comment_content" class="w-full p-3 mb-2 rounded-lg border text-sm" placeholder="Add a comment" required></textarea>
                                    <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                    <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm w-full sm:w-auto">Comment</button>
                                </form>
                                <div class="comment-list">
                                    <?php
                                    $commentsQuery = "SELECT c.content, c.created_at, u.first_name, u.last_name, u.profile_pic
                                                      FROM comments c
                                                      JOIN users u ON c.user_id = u.id
                                                      WHERE c.post_id = ?
                                                      ORDER BY c.created_at DESC"; // Order comments oldest first
                                    $stmt = $conn->prepare($commentsQuery);
                                    $stmt->bind_param("i", $post['post_id']);
                                    $stmt->execute();
                                    $commentsResult = $stmt->get_result();
                                    $comment_count = $commentsResult->num_rows; // Count comments
                                    $comments = $commentsResult->fetch_all(MYSQLI_ASSOC); // Fetch all comments

                                    foreach ($comments as $index => $comment):
                                    ?>
                                        <div class="flex items-start mb-4 <?= ($comment_count > 2 && $index < ($comment_count - 2)) ? 'hidden md:flex' : '' ?>">
                                            <img src="<?= htmlspecialchars($comment['profile_pic'] ?? './assets/055a91979264664a1ee12b9453610d82.jpg') ?>" alt="Profile" class="w-8 h-8 rounded-full mr-2 object-cover">
                                            <div>
                                                <p class="font-bold text-sm"><?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?></p>
                                                <p class="text-xs text-gray-500"><?= date('F j, Y, g:i a', strtotime($comment['created_at'] ?? 'now')) ?></p>
                                                <p class="mt-1 text-sm"><?= htmlspecialchars($comment['content'] ?? '') ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($comment_count > 2): ?> <button class="see-more-comments md:hidden">See More</button> <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-gray-500 text-center">No posts found<?= !empty($searchQuery) ? ' for "' . htmlspecialchars($searchQuery) . '"' : '' ?>.</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Sidebar Toggle
        try {
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const closeSidebarBtn = document.getElementById('closeSidebarBtn');

            if (hamburgerBtn && sidebar && sidebarOverlay && closeSidebarBtn) {
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

                hamburgerBtn.addEventListener('click', openSidebar);
                closeSidebarBtn.addEventListener('click', closeSidebar);
                sidebarOverlay.addEventListener('click', closeSidebar);

                sidebar.querySelectorAll('nav a').forEach(link => {
                    link.addEventListener('click', closeSidebar);
                });

                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 1024) {
                        closeSidebar();
                    }
                });
            } else {
                console.error('Missing sidebar element(s). Hamburger menu may not work.');
            }
        } catch (error) {
            console.error('Sidebar initialization error:', error);
        }


        // Create Post Form Toggle
        try {
            const createPostButtonDesktop = document.getElementById('createPostButton'); // Desktop button
            const createPostButtonMobile = document.getElementById('createPostButtonMobile'); // Mobile button
            const createPostForm = document.getElementById('createPostForm');

            if ((createPostButtonDesktop || createPostButtonMobile) && createPostForm) {
                function toggleCreatePostForm() {
                     createPostForm.classList.toggle('hidden'); // Toggle visibility
                }

                if(createPostButtonDesktop) {
                     createPostButtonDesktop.addEventListener('click', toggleCreatePostForm);
                }
                if(createPostButtonMobile) {
                    createPostButtonMobile.addEventListener('click', toggleCreatePostForm);
                }

            } else {
                console.error('Missing create post button(s) or form.');
            }
        } catch (error) {
            console.error('Create post initialization error:', error);
        }


        // See More Comments Toggle
        try {
            const seeMoreButtons = document.querySelectorAll('.see-more-comments');

            seeMoreButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const commentList = button.previousElementSibling;
                    commentList.classList.add('show-all'); // Always show all comments when button is clicked
                    button.style.display = 'none'; // Hide the button after clicking
                });
            });
        } catch (error) {
            console.error('See more comments initialization error:', error);
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>