<?php
session_start();
include 'db.php'; // Include the database connection file

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch current user's profile picture
$user_id = $_SESSION['user_id'];
$sql = "SELECT profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
// Use a default image if profile_pic is null or empty
$user_profile_pic = !empty($user['profile_pic']) ? $user['profile_pic'] : './assets/055a91979264664a1ee12b9453610d82.jpg';

// Get unread messages count
$unreadQuery = "SELECT COUNT(*) as unread
                FROM messages
                WHERE receiver_id = ?
                AND read_status = 0";
$stmtUnread = $conn->prepare($unreadQuery);
$stmtUnread->bind_param("i", $user_id);
$stmtUnread->execute();
$unreadResult = $stmtUnread->get_result();
$unreadCount = $unreadResult->fetch_assoc()['unread'] ?? 0;

// Count pending requests
$pendingRequestsCount = 0;
$sql = "SELECT COUNT(*) AS count
        FROM requests
        WHERE owner_id = ? AND status = 'Pending'";
$stmtPending = $conn->prepare($sql);
$stmtPending->bind_param("i", $_SESSION['user_id']);
$stmtPending->execute();
$pendingRequestsCount = $stmtPending->get_result()->fetch_assoc()['count'] ?? 0;

// Fetch latest books (e.g., last 4 added books)
$latestBooksQuery = "
    SELECT books.*, CONCAT(users.first_name, ' ', users.last_name) AS owner_name
    FROM books
    JOIN users ON books.owner_id = users.id
    WHERE books.book_id NOT IN (SELECT book_id FROM deleted_books)
    ORDER BY date_added DESC LIMIT 4
";
$latestBooksResult = $conn->query($latestBooksQuery);

$latestBooks = [];
if ($latestBooksResult && $latestBooksResult->num_rows > 0) {
    while ($row = $latestBooksResult->fetch_assoc()) {
        $latestBooks[] = $row;
    }
}

// Fetch all books (for search/filter)
$booksQuery = "
    SELECT books.*, CONCAT(users.first_name, ' ', users.last_name) AS owner_name
    FROM books
    JOIN users ON books.owner_id = users.id
    WHERE books.book_id NOT IN (SELECT book_id FROM deleted_books)
";
$booksResult = $conn->query($booksQuery);

$books = [];
if ($booksResult && $booksResult->num_rows > 0) {
    while ($row = $booksResult->fetch_assoc()) {
        $books[] = $row;
    }
}

// Handle search
$searchQuery = $_GET['search'] ?? '';
$searchResults = [];

if (!empty($searchQuery)) {
    $searchQuery = strtolower(trim($searchQuery));
    $searchResults = array_filter($books, function ($book) use ($searchQuery) {
        return stripos($book['title'] ?? '', $searchQuery) !== false ||
               stripos($book['author'] ?? '', $searchQuery) !== false;
    });
    $latestBooks = [];
} else {
    $latestBooksResult = $conn->query("
        SELECT books.*, CONCAT(users.first_name, ' ', users.last_name) AS owner_name
        FROM books
        JOIN users ON books.owner_id = users.id
        WHERE books.book_id NOT IN (SELECT book_id FROM deleted_books)
        ORDER BY date_added DESC LIMIT 4
    ");
    $latestBooks = [];
    if ($latestBooksResult && $latestBooksResult->num_rows > 0) {
        while ($row = $latestBooksResult->fetch_assoc()) {
            $latestBooks[] = $row;
        }
    }
}

// Handle category filtering
$category = $_GET['category'] ?? 'All';
$filteredBooks = ($category === 'All') ? $books : array_filter($books, fn($book) => ($book['genre'] ?? '') === $category);

// Handle book selection for side panel
$selectedBook = null;
if (isset($_GET['title'])) {
    $selectedBookArray = array_filter($books, fn($book) => ($book['title'] ?? '') === urldecode($_GET['title']));
    $selectedBook = reset($selectedBookArray);
}

// Handle book request
$successMsg = "";
$errorMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $book_id = $_POST['book_id'];
    $requester_id = $_SESSION['user_id'];

    $sql = "SELECT owner_id, availability_status, title FROM books WHERE book_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        $owner_id = $result['owner_id'];
        $availability_status = $result['availability_status'] ?? 'Available';
        $bookTitleForMsg = $result['title'] ?? 'the book';

        if ($availability_status === 'Available') {
            if ($owner_id == $requester_id) {
                $errorMsg = "You cannot request your own book.";
            } else {
                $checkReqSql = "SELECT request_id FROM requests WHERE book_id = ? AND requester_id = ? AND (status = 'Pending' OR status = 'Accepted')";
                $checkReqStmt = $conn->prepare($checkReqSql);
                $checkReqStmt->bind_param("ii", $book_id, $requester_id);
                $checkReqStmt->execute();
                $existingRequest = $checkReqStmt->get_result()->fetch_assoc();

                if ($existingRequest) {
                    $successMsg = "You have already requested this book.";
                } else {
                    $insertSql = "INSERT INTO requests (book_id, requester_id, owner_id, status) VALUES (?, ?, ?, 'Pending')";
                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->bind_param("iii", $book_id, $requester_id, $owner_id);
                    if ($insertStmt->execute()) {
                        $successMsg = "Request for \"" . htmlspecialchars($bookTitleForMsg) . "\" sent successfully!";
                    } else {
                        $errorMsg = "Error sending request.";
                        error_log("Database error inserting request: " . $conn->error);
                    }
                }
            }
        } else {
            $errorMsg = "This book is currently unavailable.";
        }
    } else {
        $errorMsg = "Book not found.";
    }

    $_SESSION['success_msg'] = $successMsg;
    $_SESSION['error_msg'] = $errorMsg;

    $redirectUrl = 'home.php';
    if ($selectedBook) {
        $redirectUrl .= '?title=' . urlencode($selectedBook['title']);
    }
    header("Location: " . $redirectUrl);
    exit;
}

// Retrieve flash messages if any
$successMsg = $_SESSION['success_msg'] ?? '';
$errorMsg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg']);
unset($_SESSION['error_msg']);

// Fetch posts
$postsQuery = "
    SELECT p.post_id, p.content, p.image_url, p.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS username,
           u.profile_pic,
           (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) AS likes_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) AS comments_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC LIMIT 4
";
$postsResult = $conn->query($postsQuery);

$latestPosts = [];
if ($postsResult) {
    while ($post = $postsResult->fetch_assoc()) {
        $latestPosts[] = $post;
    }
} else {
    error_log("Error fetching posts: " . $conn->error);
}

// Fetch latest events (e.g., last 4 approved events)
$latestEventsQuery = "
    SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) AS creator_name
    FROM events e
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'Approved'
    ORDER BY e.event_date ASC, e.event_time ASC LIMIT 4
";
$latestEventsResult = $conn->query($latestEventsQuery);

$latestEvents = [];
if ($latestEventsResult && $latestEventsResult->num_rows > 0) {
    while ($row = $latestEventsResult->fetch_assoc()) {
        $latestEvents[] = $row;
    }
}

// Get current page for sidebar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
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
                display: none !important;
            }

            body.lg\:flex-row {
                flex-direction: row;
            }

            header.lg\:flex {
                display: flex !important;
            }
        }

        #bookDetailsOverlay {
            background-color: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(2px);
        }
    </style>
</head>

<body class="bg-gray-100 font-[Poppins] flex flex-col lg:flex-row">
    <header class="lg:hidden flex items-center justify-between bg-gradient-to-r from-purple-800 to-blue-900 text-white rounded-b-xl p-4 shadow-md w-full">
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

    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <main id="mainContent" class="flex-1 p-6 overflow-x-hidden">
        <header class="hidden lg:flex items-center justify-between mb-6 bg-gradient-to-r from-purple-100 to-blue-900 text-white rounded-xl p-6 shadow-md">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold leading-snug text-blue-900">Keep books moving,</h1>
                    <p class="text-base font-light -mt-1 text-blue-600">Keep stories alive.</p>
                </div>
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

        <div class="mb-6">
            <input type="text" id="searchInput" placeholder="Search your favorite books" class="w-full px-4 py-2 rounded-lg border border-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-900">
        </div>

        <?php if ($successMsg): ?>
            <div class="bg-green-200 text-green-800 p-3 rounded-lg mb-4">
                <?= htmlspecialchars($successMsg) ?>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="bg-red-200 text-red-800 p-3 rounded-lg mb-4">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <div id="searchResults" class="bg-white p-4 rounded-lg shadow-md mb-6 hidden">
            <h3 class="text-lg font-bold mb-2">Search Results</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php if (!empty($searchResults)): ?>
                    <?php foreach ($searchResults as $book): ?>
                        <a href="home.php?title=<?= urlencode($book['title']) ?>" class="text-center flex flex-col items-center">
                            <img src="<?= htmlspecialchars($book['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($book['title'] ?? '') ?>" class="w-36 h-48 object-cover rounded-lg">
                            <p class="font-bold mt-2"><?= htmlspecialchars($book['title'] ?? '') ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php elseif (!empty($_GET['search'])): ?>
                    <p class="col-span-full text-center text-gray-500">No books found for "<?= htmlspecialchars($_GET['search']) ?>".</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($_GET['search'])): ?>
            <div id="latestBooksSection" class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h3 class="text-xl font-bold mb-8">Latest Books</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php if (!empty($latestBooks)): ?>
                        <?php foreach ($latestBooks as $book): ?>
                            <a href="home.php?title=<?= urlencode($book['title']) ?>" class="text-center cursor-pointer flex flex-col items-center justify-center">
                                <img src="<?= htmlspecialchars($book['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($book['title'] ?? '') ?>" class="w-48 h-64 object-cover rounded-lg">
                                <p class="font-bold mt-2 text-center"><?= htmlspecialchars($book['title'] ?? '') ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="col-span-full text-center text-gray-500">No latest books available.</p>
                    <?php endif; ?>
                </div>

                <div class="mt-6 text-right">
                    <a href="books.php" class="bg-blue-900 text-white py-2 px-6 rounded-md hover:bg-blue-700">View All Books</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-xl font-bold mb-8">Latest Posts</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php
                if (!empty($latestPosts)):
                    foreach ($latestPosts as $post):
                        $shortContent = strlen($post['content'] ?? '') > 50 ? substr($post['content'] ?? '', 0, 50) . '...' : ($post['content'] ?? '');
                        $postProfilePic = !empty($post['profile_pic']) ? $post['profile_pic'] : './assets/055a91979264664a1ee12b9453610d82.jpg';
                        ?>
                        <div class="flex flex-col items-center justify-center p-4 border rounded-lg shadow-sm">
                            <?php if (!empty($post['image_url'])): ?>
                                <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="Post Image" class="w-full h-40 object-cover rounded-lg mb-4">
                            <?php endif; ?>

                            <p class="font-bold text-center mb-2"><?= htmlspecialchars($shortContent) ?></p>

                            <div class="flex items-center justify-center mb-2">
                                <img src="<?= htmlspecialchars($postProfilePic) ?>?t=<?= time() ?>" alt="Profile Pic" class="w-8 h-8 rounded-full border-2 border-blue-900 mr-2 object-cover">
                                <p class="text-sm text-gray-700"><?= htmlspecialchars($post['username'] ?? 'Unknown User') ?></p>
                            </div>

                            <div class="text-center text-sm text-gray-500 mb-2">
                                ❤️ <?= $post['likes_count'] ?? 0 ?> Likes | 💬 <?= $post['comments_count'] ?? 0 ?> Comments
                            </div>

                            <a href="posts.php?post_id=<?= $post['post_id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm">See more</a>
                        </div>
                    <?php endforeach;
                else: ?>
                    <p class="col-span-full text-center text-gray-500">No posts available.</p>
                <?php endif; ?>
            </div>

            <div class="mt-6 text-right">
                <a href="posts.php" class="bg-blue-900 text-white py-2 px-6 rounded-md hover:bg-blue-700">View All Posts</a>
            </div>
        </div>

        <?php if (empty($_GET['search'])): ?>
            <div id="latestEventsSection" class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h3 class="text-xl font-bold mb-8">Latest Events</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php if (!empty($latestEvents)): ?>
                        <?php foreach ($latestEvents as $event): ?>
                            <a href="events.php" class="text-center cursor-pointer flex flex-col items-center justify-center">
                                <?php if (!empty($event['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($event['image_url']) ?>" alt="<?= htmlspecialchars($event['event_name']) ?>" class="w-48 h-64 object-cover rounded-lg">
                                <?php else: ?>
                                    <div class="w-48 h-64 bg-gray-200 flex items-center justify-center rounded-lg">
                                        <i class="fas fa-calendar-day text-4xl text-gray-400"></i>
                                    </div>
                                <?php endif; ?>
                                <p class="font-bold mt-2 text-center"><?= htmlspecialchars($event['event_name']) ?></p>
                                <p class="text-sm text-gray-500 mt-1"><?= date('F j, Y', strtotime($event['event_date'])) ?></p>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($event['venue']) ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="col-span-full text-center text-gray-500">No upcoming events available. Check the <a href="events.php" class="text-blue-600 hover:underline">Events page</a> for more.</p>
                    <?php endif; ?>
                </div>

                <div class="mt-6 text-right">
                    <a href="events.php" class="bg-blue-900 text-white py-2 px-6 rounded-md hover:bg-blue-700">View All Events</a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // JavaScript for Sidebar Toggle
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

        // JavaScript for Search Functionality (AJAX)
        document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById("searchInput");
            const searchResultsContainer = document.getElementById("searchResults");
            const latestBooksSection = document.getElementById("latestBooksSection");

            searchInput.addEventListener("input", function () {
                let query = this.value.trim();

                if (query.length > 1) {
                    fetch(`home.php?search=${encodeURIComponent(query)}`)
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newSearchResultsContent = doc.getElementById('searchResults').innerHTML;

                            searchResultsContainer.innerHTML = newSearchResultsContent;
                            searchResultsContainer.classList.remove("hidden");

                            if (latestBooksSection) {
                                latestBooksSection.classList.add("hidden");
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching search results:', error);
                        });
                } else {
                    searchResultsContainer.classList.add("hidden");
                    if (latestBooksSection) {
                        latestBooksSection.classList.remove("hidden");
                    }
                    const url = new URL(window.location.href);
                    if (url.searchParams.has('search') && url.searchParams.get('search').length <= 1) {
                        url.searchParams.delete('search');
                        window.location.href = url.toString();
                    }
                }
            });

            const urlParams = new URLSearchParams(window.location.search);
            const initialSearchQuery = urlParams.get('search');
            if (initialSearchQuery && initialSearchQuery.length > 1) {
                searchResultsContainer.classList.remove('hidden');
                if (latestBooksSection) {
                    latestBooksSection.classList.add('hidden');
                }
                searchInput.value = initialSearchQuery;
            }
        });
    </script>
</body>
</html>