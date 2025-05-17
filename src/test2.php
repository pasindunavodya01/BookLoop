<?php
include 'db.php'; // Include the database connection file

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
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
$takenBooksQuery = "SELECT books.title, books.author, books.image_url, books.book_id, users.id AS requester_name, r.request_id 
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

$user_id = $_SESSION['user_id'];

// Fetch books owned by the user
$sql = "SELECT * FROM books WHERE owner_id = ? AND book_id NOT IN (SELECT book_id FROM deleted_books)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$latestBooks = $result->fetch_all(MYSQLI_ASSOC);

// Fetch history for the user (returned requests as owner or borrower)
$sql = "
    SELECT r.request_id, r.book_id, r.requester_id AS borrower_id, b.owner_id, r.created_at AS timestamp, b.title, b.author, b.image_url
    FROM requests r
    JOIN books b ON r.book_id = b.book_id
    WHERE r.status = 'Returned' AND (r.requester_id = ? OR b.owner_id = ?)
    ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$history = $result->fetch_all(MYSQLI_ASSOC);

// Handle book deletion
if (isset($_POST['delete_book'])) {
    $book_id = $_POST['delete_book'];
    if (!empty($book_id)) {
        $sql = "INSERT INTO deleted_books (book_id) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $book_id);
        if ($stmt->execute()) {
            echo "✅ Book marked as deleted.";
            header("Refresh:0");
        } else {
            echo "❌ Error: {$stmt->error}";
        }
    } else {
        echo "❌ Error: Book ID is missing.";
    }
}

// Remove request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_request'])) {
    $book_id = intval($_POST['book_id']);
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("DELETE FROM requests WHERE book_id = ? AND requester_id = ?");
    $stmt->bind_param("ii", $book_id, $user_id);
    $stmt->execute();
}
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
        .main-nav {
            @apply mb-6 bg-white p-2 rounded-lg shadow flex gap-2 sticky top-0 z-40 border-b;
        }

        .main-nav-link {
            @apply px-4 py-2 rounded-t-lg text-gray-600 hover:bg-gray-200 transition;
            border-left: 1px solid #D1D5DB;
            border-top: 1px solid #D1D5DB;
            margin-bottom: -1px;
        }

        .main-nav-link:first-child {
            border-left: none;
        }

        .tab-btn {
            @apply px-4 py-2 rounded-t-lg text-gray-600 hover:bg-gray-200 transition duration-200;
        }

        .tab-btn.tab-active {
            @apply bg-blue-600 text-white font-semibold;
            border-bottom: 2px solid #74C0FC;
        }
    </style>
</head>
<body class="bg-gray-100 font-[Poppins] flex">
    <aside class="w-64 bg-white shadow-lg h-screen p-6 sticky top-0">
        <h2 class="mb-8 flex items-center">
            <img src="./assets/landing.jpeg" alt="Book Icon" class="w-40 h-40 object-cover rounded-full">
        </h2>
        <nav>
            <ul>
                <li><a href="home.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-house mr-3" style="color: #74C0FC;"></i>Home</a></li>
                <li><a href="books.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-book mr-3" style="color: #74C0FC;"></i> Books</a></li>
                <li><a href="listbooks.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-plus mr-3" style="color: #74C0FC;"></i> List Books</a></li>
                <li><a href="requests.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-envelope mr-3" style="color: #74C0FC;"></i> Requests</a></li>
                <li>
                    <a href="message.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 relative">
                        <i class="fa-solid fa-message mr-2" style="color: #74C0FC;"></i> Message
                        <?php if ($unreadCount > 0): ?>
                            <span class="absolute top-2 left-6 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?= $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="home.php?category=All" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-calendar-days mr-3" style="color: #74C0FC;"></i> Events</a></li>
                <li><a href="posts.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-image mr-3" style="color: #74C0FC;"></i> Posts</a></li>
                <li><a href="#" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-ticket mr-3" style="color: #74C0FC;"></i> Support</a></li>
                <li><a href="logout.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-right-from-bracket mr-3" style="color: #74C0FC;"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="flex-1 p-6 overflow-x-hidden">
        <header class="flex items-center justify-between mb-6">
            <input type="text" placeholder="Search your favorite books" class="p-3 rounded-full border border-gray-300 w-2/4">
            <div class="flex items-center gap-4">
                <a href="requests.php">
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
                    <img src="./assets/055a91979264664a1ee12b9453610d82.jpg" alt="User" class="w-10 h-10 rounded-full border-2 border-blue-900 cursor-pointer">
                </a>
            </div>
        </header>
        <nav class="mb-6 bg-white p-2 rounded-lg shadow flex gap-2 sticky top-0 z-40 border-b">
            <a href="#my-books" data-section="my-books" class="tab-btn px-4 py-2 rounded-t-lg text-gray-600 hover:bg-gray-200 transition">📚 My Books</a>
            <a href="#requested-books" data-section="requested-books" class="tab-btn px-4 py-2 rounded-t-lg text-gray-600 hover:bg-gray-200 transition">📨 Requested Books</a>
            <a href="#exchange" data-section="exchange" class="tab-btn px-4 py-2 rounded-t-lg text-gray-600 hover:bg-gray-200 transition">🔁 Exchange</a>
            <a href="#borrows" data-section="borrows" class="tab-btn px-4 py-2 rounded-t-lg text-gray-600 hover:bg-gray-200 transition">📖 Borrowed Books</a>
            <a href="#events" data-section="events" class="tab-btn px-4 py-2 rounded-t-lg text-gray-600 hover:bg-gray-200 transition">🎈 My Events</a>
            <a href="#history" data-section="history" class="tab-btn px-4 py-2 rounded-t-lg text-gray-600 hover:bg-gray-200 transition">📜 History</a>
        </nav>

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

        <?php if (!empty($acceptedRequestedBooks) || !empty($ownedAcceptedBooks)): ?>
        <div id="exchange" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
            <?php if (!empty($acceptedRequestedBooks)): ?>
                <h4 class="text-lg font-semibold text-blue-800 mb-4">Books Requested by Me</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">
                    <?php foreach ($acceptedRequestedBooks as $book): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow text-center">
                            <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                 alt="<?= htmlspecialchars($book['title']) ?>"
                                 class="w-48 h-64 object-cover rounded-lg border-2 border-green-500 mx-auto">
                            <p class="font-bold mt-2"><?= htmlspecialchars($book['title']) ?></p>
                            <p class="text-gray-500"><?= htmlspecialchars($book['author']) ?></p>
                            <p class="text-sm font-semibold text-green-600 mb-2"><?= htmlspecialchars($book['status']) ?> - Proceed to exchange</p>
                            <a href="view_otp.php?request_id=<?= urlencode($book['request_id']) ?>" 
                               class="inline-block bg-blue-100 text-blue-700 px-4 py-1 mt-2 rounded-full text-sm hover:bg-blue-200">
                               View Pickup OTP
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($ownedAcceptedBooks)): ?>
                <h4 class="text-lg font-semibold text-blue-800 mb-4">Books Owned by Me (Accepted Requests)</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($ownedAcceptedBooks as $book): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow text-center">
                            <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                 alt="<?= htmlspecialchars($book['title']) ?>"
                                 class="w-48 h-64 object-cover rounded-lg border-2 border-green-500 mx-auto">
                            <p class="font-bold mt-2"><?= htmlspecialchars($book['title']) ?></p>
                            <p class="text-gray-500"><?= htmlspecialchars($book['author']) ?></p>
                            <p class="text-sm font-semibold text-green-600 mb-2"><?= htmlspecialchars($book['status']) ?> - Proceed to exchange</p>
                            <a href="verify_otp.php?request_id=<?= urlencode($book['request_id']) ?>" 
                               class="inline-block bg-green-100 text-green-700 px-4 py-1 mt-2 rounded-full text-sm hover:bg-green-200">
                               Verify Pickup OTP
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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

        <?php if (!empty($takenBooks) || !empty($givenBooks)): ?>
        <div id="borrows" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-xl font-bold text-indigo-700 mb-6">Borrowed Books</h3>
            <?php if (!empty($takenBooks)): ?>
                <h4 class="text-lg font-semibold text-purple-800 mb-4">Books Taken by Me</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">
                    <?php foreach ($takenBooks as $book): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow text-center">
                            <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                 alt="<?= htmlspecialchars($book['title']) ?>"
                                 class="w-48 h-64 object-cover rounded-lg border-2 border-indigo-500 mx-auto">
                            <p class="font-bold mt-2"><?= htmlspecialchars($book['title']) ?></p>
                            <p class="text-gray-500"><?= htmlspecialchars($book['author']) ?></p>
                            <p class="text-sm font-semibold text-indigo-600 mt-2">✔️ Picked Up</p>
                            <a href="verify_return_otp.php?request_id=<?= urlencode($book['request_id']) ?>" 
                               class="inline-block bg-red-100 text-red-700 px-4 py-1 mt-2 rounded-full text-sm hover:bg-red-200">
                               Return Book
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($givenBooks)): ?>
                <h4 class="text-lg font-semibold text-purple-800 mb-4">Books Given by Me</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($givenBooks as $book): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow text-center">
                            <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                 alt="<?= htmlspecialchars($book['title']) ?>"
                                 class="w-48 h-64 object-cover rounded-lg border-2 border-indigo-500 mx-auto">
                            <p class="font-bold mt-2"><?= htmlspecialchars($book['title']) ?></p>
                            <p class="text-gray-500"><?= htmlspecialchars($book['author']) ?></p>
                            <p class="text-sm font-semibold text-indigo-600 mt-2">✔️ Picked Up</p>
                            <a href="view_return_otp.php?request_id=<?= urlencode($book['request_id']) ?>" 
                               class="inline-block bg-blue-100 text-blue-700 px-4 py-1 mt-2 rounded-full text-sm hover:bg-blue-200">
                               View Return OTP
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div id="events" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-xl font-bold mb-8">My Events</h3>
            <p class="text-gray-600">Content for My Events section will go here.</p>
        </div>

        <div id="history" class="section hidden bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-xl font-bold mb-8">History</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php if (!empty($history)): ?>
                    <?php foreach ($history as $entry): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow text-center">
                            <img src="<?= htmlspecialchars($entry['image_url']) ?>"
                                 alt="<?= htmlspecialchars($entry['title']) ?>"
                                 class="w-48 h-64 object-cover rounded-lg mx-auto">
                            <p class="font-bold mt-2"><?= htmlspecialchars($entry['title']) ?></p>
                            <p class="text-gray-500"><?= htmlspecialchars($entry['author']) ?></p>
                            <p class="text-sm font-semibold text-green-600 mt-2">
                                Returned on <?= date('F j, Y, g:i a', strtotime($entry['timestamp'])) ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                <?= $entry['owner_id'] == $user_id ? 'Lent to user ' . $entry['borrower_id'] : 'Borrowed from user ' . $entry['owner_id'] ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-600">No returned books yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const sections = document.querySelectorAll('.section');

            tabButtons.forEach(button => {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    const targetId = this.getAttribute('data-section');
                    sections.forEach(section => section.classList.add('hidden'));
                    document.getElementById(targetId).classList.remove('hidden');
                    tabButtons.forEach(btn => btn.classList.remove('tab-active'));
                    this.classList.add('tab-active');
                });
            });

            if (tabButtons.length > 0) {
                tabButtons[0].classList.add('tab-active');
                document.getElementById(tabButtons[0].getAttribute('data-section')).classList.remove('hidden');
            }
        });
    </script>
</body>
</html>