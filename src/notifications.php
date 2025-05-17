<?php
session_start();
include 'db.php'; // Database connection
require_once 'get_unread_messages.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get unread messages count
$unreadCount = getUnreadMessagesCount($conn, $user_id);

// Fetch pending book requests (limited to 6)
$sql = "SELECT r.request_id, r.created_at, b.title, b.image_url, u.first_name AS requester_name
        FROM requests r
        JOIN books b ON r.book_id = b.book_id
        JOIN users u ON r.requester_id = u.id
        WHERE r.owner_id = ? AND r.status = 'Pending'
        ORDER BY r.created_at DESC LIMIT 6";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$book_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch likes on user's posts (limited to 6, ordered by like_id)
$sql = "SELECT l.like_id, u.first_name AS liker_name, p.post_id
        FROM likes l
        JOIN posts p ON l.post_id = p.post_id
        JOIN users u ON l.user_id = u.id
        WHERE p.user_id = ?
        ORDER BY l.like_id DESC LIMIT 4";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$likes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch comments on user's posts (limited to 6, ordered by comment_id)
$sql = "SELECT c.comment_id, c.content, u.first_name AS commenter_name, p.post_id
        FROM comments c
        JOIN posts p ON c.post_id = p.post_id
        JOIN users u ON c.user_id = u.id
        WHERE p.user_id = ?
        ORDER BY c.comment_id DESC LIMIT 4";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch latest events (limited to 6 approved events)
$sql = "SELECT e.event_id, e.event_name, e.event_date, CONCAT(u.first_name, ' ', u.last_name) AS creator_name
        FROM events e
        JOIN users u ON e.user_id = u.id
        WHERE e.status = 'Approved'
        ORDER BY e.event_date ASC, e.event_time ASC LIMIT 6";
$stmt = $conn->prepare($sql);
$stmt->execute();
$latest_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle request actions (Accept/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];

    $status = $action === 'accept' ? 'Accepted' : 'Rejected';
    $sql = "UPDATE requests SET status = ? WHERE request_id = ? AND owner_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $status, $request_id, $user_id);
    if ($stmt->execute()) {
        $success_message = "Request " . ($action === 'accept' ? "accepted" : "rejected") . " successfully.";
        // Refresh requests
        $sql = "SELECT r.request_id, r.created_at, b.title, b.image_url, u.first_name AS requester_name
                FROM requests r
                JOIN books b ON r.book_id = b.book_id
                JOIN users u ON r.requester_id = u.id
                WHERE r.owner_id = ? AND r.status = 'Pending'
                ORDER BY r.created_at DESC LIMIT 6";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $book_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $error_message = "Failed to process request.";
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BookLoop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="output.css" rel="stylesheet">
    <style>
        /* Mobile-first approach */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 256px;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
            background-color: white;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            z-index: 50;
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

        /* Header */
        header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: linear-gradient(to right, #6b7280, #1e3a8a);
            color: white;
            border-radius: 0.5rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .hamburger-btn {
            font-size: 1.5rem;
            background: none;
            border: none;
            color: #74C0FC;
            cursor: pointer;
        }

        /* Notification Cards */
        .notification-card {
            display: flex;
            flex-direction: column;
            padding: 0.75rem;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            gap: 0.5rem;
        }

        .notification-card img {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 0.25rem;
        }

        .notification-content {
            flex: 1;
            font-size: 0.875rem;
        }

        .notification-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s;
            flex: 1;
            text-align: center;
        }

        .action-btn:hover {
            opacity: 0.9;
        }

        .error, .success {
            font-size: 0.875rem;
            padding: 0.5rem;
            border-radius: 0.25rem;
        }

        .timestamp {
            color: #6b7280;
            font-size: 0.75rem;
        }

        /* Main Content */
        main {
            padding: 1rem;
            flex: 1;
        }

        section {
            margin-bottom: 1.5rem;
        }

        h2 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        /* Desktop Styles */
        @media (min-width: 1024px) {
            body {
                flex-direction: row;
            }

            .sidebar {
                position: static;
                transform: translateX(0);
                flex-shrink: 0;
                height: 100vh;
                z-index: auto;
            }

            .sidebar-overlay {
                display: none !important;
            }

            header {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                padding: 1.5rem;
            }

            .header-content {
                flex: 1;
            }

            .hamburger-btn {
                display: none;
            }

            .notification-card {
                flex-direction: row;
                align-items: center;
                padding: 1rem;
            }

            .notification-actions {
                flex-wrap: nowrap;
            }

            .action-btn {
                flex: none;
                padding: 0.5rem 1rem;
            }

            main {
                padding: 1.5rem;
            }

            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-[Poppins] flex min-h-screen">
    <!-- Sidebar -->
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

    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- Main Content -->
    <main class="flex-1 p-6 overflow-x-hidden">
        <!-- Header -->
        <header class="flex items-center justify-between mb-6">
            <div class="header-content">
                <button class="hamburger-btn lg:hidden" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="text-2xl font-bold">Notifications</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="notifications.php" aria-label="Notifications">
                    <span class="text-2xl cursor-pointer flex items-center relative">
                        <i class="fa-solid fa-bell" style="color: #74C0FC;"></i>
                        <?php if (count($book_requests) > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?= htmlspecialchars(count($book_requests)) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </a>
                <a href="account.php" aria-label="User Profile">
                    <img src="./assets/055a91979264664a1ee12b9453610d82.jpg" alt="User Profile" class="w-10 h-10 rounded-full border-2 border-blue-900 cursor-pointer">
                </a>
            </div>
        </header>

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <p class="success"><?= htmlspecialchars($success_message) ?></p>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <p class="error"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>

        <!-- Book Requests -->
        <section class="mb-8">
            <h2 class="text-xl font-semibold mb-4">Book Requests</h2>
            <?php if (!empty($book_requests)): ?>
                <?php foreach ($book_requests as $request): ?>
                    <div class="notification-card">
                        <img src="<?= htmlspecialchars($request['image_url']) ?>" alt="<?= htmlspecialchars($request['title']) ?>">
                        <div class="notification-content">
                            <p class="font-medium"><?= htmlspecialchars($request['requester_name']) ?> requested your book <strong><?= htmlspecialchars($request['title']) ?></strong></p>
                            <p class="timestamp"><?= date('M d, Y H:i', strtotime($request['created_at'])) ?></p>
                        </div>
                        <div class="notification-actions">
                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="action-btn bg-green-600 text-white">Accept</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="action-btn bg-red-600 text-white">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-500">No pending book requests.</p>
            <?php endif; ?>
        </section>

        <!-- Post Interactions -->
        <section class="mb-8">
            <h2 class="text-xl font-semibold mb-4">Post Interactions</h2>
            <?php if (!empty($likes) || !empty($comments)): ?>
                <?php foreach ($likes as $like): ?>
                    <div class="notification-card">
                        <i class="fas fa-heart text-red-500 text-2xl mr-4"></i>
                        <div class="notification-content">
                            <p class="font-medium"><?= htmlspecialchars($like['liker_name']) ?> liked your post.</p>
                        </div>
                        <div class="notification-actions">
                            <a href="posts.php?post_id=<?= $like['post_id'] ?>" class="action-btn bg-blue-600 text-white">View Post</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="notification-card">
                        <i class="fas fa-comment text-blue-500 text-2xl mr-4"></i>
                        <div class="notification-content">
                            <p class="font-medium"><?= htmlspecialchars($comment['commenter_name']) ?> commented: "<?= htmlspecialchars(substr($comment['content'], 0, 50)) ?><?php if (strlen($comment['content']) > 50) echo '...'; ?>"</p>
                        </div>
                        <div class="notification-actions">
                            <a href="posts.php?post_id=<?= $comment['post_id'] ?>" class="action-btn bg-blue-600 text-white">View Post</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-500">No recent post interactions.</p>
            <?php endif; ?>
        </section>

        <!-- Latest Events -->
        <section class="mb-8">
            <h2 class="text-xl font-semibold mb-4">Latest Events</h2>
            <?php if (!empty($latest_events)): ?>
                <?php foreach ($latest_events as $event): ?>
                    <div class="notification-card">
                        <i class="fas fa-calendar-days text-green-500 text-2xl mr-4"></i>
                        <div class="notification-content">
                            <p class="font-medium"><strong><?= htmlspecialchars($event['event_name']) ?></strong> by <?= htmlspecialchars($event['creator_name']) ?> (Event on <?= date('M d, Y', strtotime($event['event_date'])) ?>).</p>
                        </div>
                        <div class="notification-actions">
                            <a href="events.php?event_id=<?= $event['event_id'] ?>" class="action-btn bg-blue-600 text-white">View Event</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-500">No upcoming events.</p>
            <?php endif; ?>
        </section>
    </main>

    <!-- JavaScript -->
    <script>
        // Sidebar Toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.querySelector('.sidebar');
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

        hamburgerBtn.addEventListener('click', function() {
            if (sidebar.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

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
    </script>
</body>
</html>