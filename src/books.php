<?php
include 'db.php'; // Include the database connection file

// Get unread messages count
$user_id = $_SESSION['user_id'];
$unreadQuery = "SELECT COUNT(*) as unread 
                FROM messages 
                WHERE receiver_id = ? 
                AND read_status = 0
                AND is_read = 0";
$stmtUnread = $conn->prepare($unreadQuery);
$stmtUnread->bind_param("i", $user_id);
$stmtUnread->execute();
$unreadResult = $stmtUnread->get_result();
$unreadCount = $unreadResult->fetch_assoc()['unread'];

// Count pending requests
$pendingRequestsCount = 0;
$sql = "SELECT COUNT(*) AS count 
        FROM requests 
        WHERE owner_id = ? AND status = 'Pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$pendingRequestsCount = $stmt->get_result()->fetch_assoc()['count'];

// Fetch latest books (e.g., last 4 added books)
$latestBooksQuery = "
    SELECT books.*, CONCAT(users.first_name, ' ', users.last_name) AS owner_name 
    FROM books 
    JOIN users ON books.owner_id = users.id
    WHERE books.book_id NOT IN (SELECT book_id FROM deleted_books)
      AND books.owner_id != {$_SESSION['user_id']}
    ORDER BY date_added DESC 
    LIMIT 4

";
$latestBooksResult = $conn->query($latestBooksQuery);

$latestBooks = [];
if ($latestBooksResult->num_rows > 0) {
    while ($row = $latestBooksResult->fetch_assoc()) {
        $latestBooks[] = $row;
    }
}

// Fetch all books
$booksQuery = "
    SELECT books.*, CONCAT(users.first_name, ' ', users.last_name) AS owner_name 
    FROM books 
    JOIN users ON books.owner_id = users.id
    WHERE books.book_id NOT IN (SELECT book_id FROM deleted_books)
     AND books.owner_id != {$_SESSION['user_id']}
";
$booksResult = $conn->query($booksQuery);

$books = [];
if ($booksResult->num_rows > 0) {
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
    // If there's a search query, don't show latest books section
    $latestBooks = [];
} else {
    // If no search query, ensure latest books are shown
    $latestBooksResult = $conn->query($latestBooksQuery);
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

    // Re-fetch book details
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
                // Check for existing request
                $checkReqSql = "
                SELECT request_id 
                FROM requests 
                WHERE book_id = ? 
                  AND requester_id = ? 
                  AND (status = 'Pending' OR status = 'Accepted') 
                  AND created_at >= NOW() - INTERVAL 24 HOUR
            ";
            $checkReqStmt = $conn->prepare($checkReqSql);
            $checkReqStmt->bind_param("ii", $book_id, $requester_id);
            $checkReqStmt->execute();
            $existingRequest = $checkReqStmt->get_result()->fetch_assoc();
            

            if ($existingRequest) {
                $errorMsg = "You have already requested this book within the last 24 hours. Please wait before requesting again.";
            } else {
                // Insert new request
                $sql = "INSERT INTO requests (book_id, requester_id, owner_id, status) VALUES (?, ?, ?, 'Pending')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $book_id, $requester_id, $owner_id);
                if ($stmt->execute()) {
                    $successMsg = "Request for \"" . htmlspecialchars($bookTitleForMsg) . "\" sent successfully!";
                } else {
                    $errorMsg = "Error sending request.";
                }
            }
            
            }
        } else {
            $errorMsg = "This book is currently unavailable.";
        }
    } else {
        $errorMsg = "Book not found.";
    }

    // Store flash messages in session
    $_SESSION['success_msg'] = $successMsg;
    $_SESSION['error_msg'] = $errorMsg;

    $redirectUrl = 'books.php';
    if ($selectedBook) {
        $redirectUrl .= '?title=' . urlencode($selectedBook['title']);
    }
    header("Location: " . $redirectUrl);
    exit;
}

// Retrieve flash messages
$successMsg = $_SESSION['success_msg'] ?? '';
$errorMsg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg']);
unset($_SESSION['error_msg']);

// Fetch current user's profile picture
$sql = "SELECT profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_profile_pic = !empty($user['profile_pic']) ? $user['profile_pic'] : './assets/055a91979264664a1ee12b9453610d82.jpg';

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
        /* Custom styles for sidebar transition and mobile positioning */
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
                transform: translateX(0) !important;
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

        #overlay {
  background-color: rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(2px); /* optional - adds a slight blur to the background */
}
    </style>
</head>
<body class="bg-gray-100 font-[Poppins] flex flex-col lg:flex-row">
    <!-- Mobile Header -->
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
                    <?php if ($pendingRequestsCount > 0): ?>
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

    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- Main Content -->
    <main id="mainContent" class="flex-1 p-6 overflow-x-hidden">
        <!-- Desktop Header -->
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
                        <?php if ($pendingRequestsCount > 0): ?>
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

        <!-- Search Bar -->
        <div class="mb-6">
            <input type="text" id="searchInput" placeholder="Search your favorite books" class="w-full px-4 py-2 rounded-lg border border-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-900">
        </div>

        <!-- Flash Messages -->
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

        <!-- Search Results Section -->
        <div id="searchResults" class="bg-white p-4 rounded-lg shadow-md mb-6 hidden">
            <h3 class="text-lg font-bold mb-2">Search Results</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php if (!empty($searchResults)): ?>
                    <?php foreach ($searchResults as $book): ?>
                        <a href="books.php?title=<?= urlencode($book['title']) ?>" class="text-center flex flex-col items-center">
                            <img src="<?= htmlspecialchars($book['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($book['title'] ?? '') ?>" class="w-36 h-48 object-cover rounded-lg">
                            <p class="font-bold mt-2"><?= htmlspecialchars($book['title'] ?? '') ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php elseif (!empty($_GET['search'])): ?>
                    <p class="col-span-full text-center text-gray-500">No books found for "<?= htmlspecialchars($_GET['search']) ?>".</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Latest Books Section -->
        <?php if (empty($_GET['search'])): ?>
            <div id="latestBooksSection" class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h3 class="text-xl font-bold mb-8">Latest Books</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php if (!empty($latestBooks)): ?>
                        <?php foreach ($latestBooks as $book): ?>
                            <a href="books.php?title=<?= urlencode($book['title']) ?>" class="text-center cursor-pointer flex flex-col items-center justify-center">
                                <img src="<?= htmlspecialchars($book['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($book['title'] ?? '') ?>" class="w-48 h-64 object-cover rounded-lg">
                                <p class="font-bold mt-2 text-center"><?= htmlspecialchars($book['title'] ?? '') ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="col-span-full text-center text-gray-500">No latest books available.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Categories and Filtered Books -->
        <div id="catergory" class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-8">Book Collection</h3>
            <div class="flex flex-wrap gap-2 mt-4 mb-8">
                <a href="books.php?category=All#catergory" class="px-4 py-2 rounded-lg <?= $category === 'All' ? 'bg-blue-900 text-white' : 'bg-white border' ?>">All</a>
                <a href="books.php?category=Fiction#catergory" class="px-4 py-2 rounded-lg <?= $category === 'Fiction' ? 'bg-blue-900 text-white' : 'bg-white border' ?>">Fiction</a>
                <a href="books.php?category=Non-Fiction#catergory" class="px-4 py-2 rounded-lg <?= $category === 'Non-Fiction' ? 'bg-blue-900 text-white' : 'bg-white border' ?>">Non-Fiction</a>
                <a href="books.php?category=Science Fiction#catergory" class="px-4 py-2 rounded-lg <?= $category === 'Science Fiction' ? 'bg-blue-900 text-white' : 'bg-white border' ?>">Science Fiction</a>
                <a href="books.php?category=Fantasy#catergory" class="px-4 py-2 rounded-lg <?= $category === 'Fantasy' ? 'bg-blue-900 text-white' : 'bg-white border' ?>">Fantasy</a>
                <a href="books.php?category=History#catergory" class="px-4 py-2 rounded-lg <?= $category === 'History' ? 'bg-blue-900 text-white' : 'bg-white border' ?>">History</a>
                <a href="books.php?category=Business#catergory" class="px-4 py-2 rounded-lg <?= $category === 'Business' ? 'bg-blue-900 text-white' : 'bg-white border' ?>">Business</a>
                <a href="books.php?category=Self-Help#catergory" class="px-4 py-2 rounded-lg <?= $category === 'Self-Help' ? 'bg-blue-900 text-white' : 'bg-white border' ?>">Self-Help</a>
            </div>

            <!-- Filtered Books -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php 
                $displayLimit = 1000;
                $totalBooks = count($filteredBooks);
                $booksToShow = array_slice($filteredBooks, 0, $displayLimit);

                if ($totalBooks === 0): ?>
                    <div class="col-span-full text-center py-8 text-lg font-semibold text-gray-600">
                        No books found in this category.
                    </div>
                <?php else: ?>
                    <?php foreach ($booksToShow as $book): ?>
                        <a href="books.php?title=<?= urlencode($book['title']) ?>" class="text-center cursor-pointer flex flex-col items-center justify-center">
                            <img src="<?= htmlspecialchars($book['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($book['title'] ?? '') ?>" class="w-48 h-64 object-cover rounded-lg">
                            <p class="font-bold mt-2 text-center"><?= htmlspecialchars($book['title'] ?? '') ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Book Details Panel (Right Side) -->
<?php if ($selectedBook): ?>
<div class="fixed inset-0 bg-gray-900 bg-opacity-25 z-50 flex justify-end" id="overlay" onclick="closeSidebox(event)">
    <aside class="w-96 bg-gray-400 text-white p-6 h-screen overflow-y-auto relative" onclick="event.stopPropagation()">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer" onclick="window.location.href='books.php'">❌</span>
        
        <img src="<?= $selectedBook['image_url'] ?? '' ?>" alt="<?= $selectedBook['title'] ?? '' ?>" class="w-48 rounded-lg mt-10 mx-auto">
        
        <h2 class="text-3xl font-bold mt-4"><?= $selectedBook['title'] ?? '' ?></h2>
        <p class="text-gray-300">by <?= $selectedBook['author'] ?? '' ?></p>
        <p class="text-sm text-gray-100 mt-1">Owned by: <strong><?= htmlspecialchars($selectedBook['owner_name']) ?></strong></p>
        <p class="mt-4 text-sm"><?= $selectedBook['description'] ?? '' ?></p>

        <p class="mt-4 text-lg font-semibold <?= ($selectedBook['availability_status'] ?? 'Available') === 'Available' ? 'text-green-400' : 'text-red-400' ?>">
            <?= $selectedBook['availability_status'] ?? 'Available' ?>
        </p>

        <?php if ($successMsg): ?>
            <div class="bg-green-200 text-green-800 p-3 rounded-lg mb-4">
                <?= $successMsg ?>
            </div>
        <?php endif; ?>

        
        <?php if ($errorMsg): ?>
            <div class="bg-red-200 text-red-800 p-3 rounded-lg mb-4">
                <?= $errorMsg ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="book_id" value="<?= $selectedBook['book_id'] ?>">
            <button 
                class="mt-6 px-6 py-3 bg-white text-blue-900 font-bold rounded-lg w-full <?= ($selectedBook['availability_status'] ?? 'Available') === 'Unavailable' ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100' ?>"
                <?= ($selectedBook['availability_status'] ?? 'Available') === 'Unavailable' ? 'disabled' : '' ?>>
                Book Now 📖
            </button>
        </form>

        <button 
            class="mt-4 px-6 py-3 bg-yellow-500 text-white font-bold rounded-lg w-full hover:bg-yellow-400"
            onclick="window.location.href='message.php?book_owner_id=<?= $selectedBook['owner_id'] ?>'">
            Chat with Owner 💬
        </button>
    </aside>
</div>

<script>
function closeSidebox(event) {
    // Check if the clicked element is the overlay itself
    if (event.target.id === 'overlay') {
        window.location.href = 'books.php';
    }
}
</script>
<?php endif; ?>

    <!-- JavaScript -->
    <script>
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

        // Search Functionality
        document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById("searchInput");
            const searchResultsContainer = document.getElementById("searchResults");
            const latestBooksSection = document.getElementById("latestBooksSection");

            searchInput.addEventListener("input", function () {
                let query = this.value.trim();

                if (query.length > 1) {
                    fetch(`books.php?search=${encodeURIComponent(query)}`)
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

            // Initial check for search query
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