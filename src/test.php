<?php
include 'db.php'; // Include the database connection file


// Get unread messages count
$user_id = $_SESSION['user_id'];
$unreadQuery = "SELECT COUNT(*) as unread 
                FROM messages 
                WHERE receiver_id = $user_id 
                AND read_status = 0
                AND is_read = 0";  // Add this condition
$unreadResult = $conn->query($unreadQuery);
$unreadCount = $unreadResult->fetch_assoc()['unread'];

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
    ORDER BY date_added DESC LIMIT 4
";
$latestBooksResult = $conn->query($latestBooksQuery);


$latestBooks = [];
if ($latestBooksResult->num_rows > 0) {
    while ($row = $latestBooksResult->fetch_assoc()) {
        $latestBooks[] = $row;
    }
}

$booksQuery = "
    SELECT books.*, CONCAT(users.first_name, ' ', users.last_name) AS owner_name 
    FROM books 
    JOIN users ON books.owner_id = users.id
    WHERE books.book_id NOT IN (SELECT book_id FROM deleted_books)
";

$booksResult = $conn->query($booksQuery);

$books = [];
if ($booksResult->num_rows > 0) {
    while ($row = $booksResult->fetch_assoc()) {
        $books[] = $row;
    }
}

$searchQuery = $_GET['search'] ?? '';
$searchResults = [];

if (!empty($searchQuery)) {
    $searchQuery = strtolower($searchQuery);
    $searchResults = array_filter($books, function ($book) use ($searchQuery) {
        return stripos($book['title'], $searchQuery) !== false || 
               stripos($book['author'], $searchQuery) !== false;
    });
}


$category = $_GET['category'] ?? 'All';
$filteredBooks = ($category === 'All') ? $books : array_filter($books, fn($book) => $book['genre'] === $category);

$selectedBook = null;
if (isset($_GET['title'])) {
    $selectedBook = array_filter($books, fn($book) => $book['title'] === urldecode($_GET['title']));
    $selectedBook = reset($selectedBook); 
}

$successMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $book_id = $_POST['book_id'];
    $requester_id = $_SESSION['user_id'];

    $sql = "SELECT owner_id FROM books WHERE book_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $owner_id = $result['owner_id'];

    $sql = "INSERT INTO requests (book_id, requester_id, owner_id, status) VALUES (?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $book_id, $requester_id, $owner_id);
    $stmt->execute();

    $successMsg = "Request sent successfully!";
}

// Fetch current user's profile picture
$user_id = $_SESSION['user_id'];
$sql = "SELECT profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_profile_pic = $user['profile_pic'] ?: './assets/055a91979264664a1ee12b9453610d82.jpg';

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
</head>
<style>
#overlay {
  background-color: rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(2px); /* optional - adds a slight blur to the background */
}
</style>
<body class="bg-gray-100 font-[Poppins] flex">
    <!-- Sidebar -->
    <aside class="w-64 bg-white shadow-lg h-screen p-6 sticky top-0">
        <h2 class="mb-8 flex items-center"> 
            <img src="./assets/landing.jpeg" 
                alt="Book Icon" 
                class="w-40 h-40 object-cover rounded-full">
        </h2>        

        <nav>
            <ul>
                <li><a href="home.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-house mr-3" style="color: #74C0FC;"></i>Home</a></li>
                <li><a href="books.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-book mr-3" style="color: #74C0FC;"></i> Books</a></li>
                <li><a href="listbooks.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-plus mr-3" style="color: #74C0FC;"></i> List Books</a></li>
                <li>
                    <a href="requests.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300">
                        <i class="fa-solid fa-envelope mr-3" style="color: #74C0FC;"></i> Requests
                    </a>
                </li>
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
                <li><a href="account.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-ticket mr-3" style="color: #74C0FC;"></i> Profile</a></li>
                <li><a href="logout.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-right-from-bracket mr-3" style="color: #74C0FC;"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main  class="flex-1 p-6 overflow-x-hidden">
        <header class="flex items-center justify-between mb-6">
            <input type="text" placeholder="Search your favorite books" class="p-3 rounded-full border border-gray-300 w-2/4">
            <div class="flex items-center gap-4">
            <a href="requests.php">
                <span class="text-2xl cursor-pointer flex items-center relative">
                    <i class="fa-solid fa-bell" style="color: #74C0FC;"></i>
                    <?php if ($pendingRequestsCount > 0): ?>
                        <!-- Notification Badge Above the Bell Icon -->
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?= $pendingRequestsCount ?>
                        </span>
                    <?php endif; ?>
                </span>
            </a>

            <a href="account.php">
                    <img src="<?= htmlspecialchars($user_profile_pic) ?>?t=<?= time() ?>" alt="User" class="w-10 h-10 rounded-full border-2 border-white cursor-pointer">
                </a>
            </div>
        </header>

        <!-- Search Results Section -->
        <div id="searchResults" class="bg-white p-4 rounded-lg shadow-md hidden mb-6"> <!-- Added mb-6 -->
            <h3 class="text-lg font-bold mb-2">Search Results</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($searchResults as $book): ?>
                    <a href="books.php?title=<?= urlencode($book['title']) ?>" class="text-center flex flex-col items-center">
                        <img src="<?= $book['image_url'] ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="w-36 h-48 object-cover rounded-lg">
                        <p class="font-bold mt-2"><?= htmlspecialchars($book['title']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Latest Books Section -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-xl font-bold mb-8">Latest</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($latestBooks as $book): ?>
                    <a href="books.php?title=<?= urlencode($book['title']) ?>" class="text-center cursor-pointer flex flex-col items-center justify-center">
                        <img src="<?= $book['image_url'] ?>" alt="<?= $book['title'] ?>" class="w-48 h-64 object-cover rounded-lg">
                        <p class="font-bold mt-2 text-center"><?= $book['title'] ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>


        <!-- Categories -->
        <!-- <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-8">Categories</h3>
            <div class="flex gap-2 mt-4 mb-8">
                <a href="books.php?category=All" class="px-4 py-2 rounded-lg <?= $category === 'All' ? 'bg-blue-900 text-white' : 'bg-white' ?>">All</a>
                <a href="books.php?category=Finance" class="px-4 py-2 rounded-lg <?= $category === 'Finance' ? 'bg-blue-900 text-white' : 'bg-white' ?>">Finance</a>
                <a href="books.php?category=Innovation" class="px-4 py-2 rounded-lg <?= $category === 'Innovation' ? 'bg-blue-900 text-white' : 'bg-white' ?>">Innovation</a>
                <a href="books.php?category=Business" class="px-4 py-2 rounded-lg <?= $category === 'Business' ? 'bg-blue-900 text-white' : 'bg-white' ?>">Business</a>
            </div> -->

            <div id="catergory" class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-8">Book Collection</h3>
            <div class="flex gap-2 mt-4 mb-8">
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

    // Check if there are no books to show
    if ($totalBooks === 0): ?>
        <div class="col-span-full text-center py-8 text-lg font-semibold text-gray-600">
            No books found in this category.
        </div>
    <?php else: ?>
        <?php foreach ($booksToShow as $book): ?>
            <a href="books.php?title=<?= urlencode($book['title']) ?>" class="text-center cursor-pointer flex flex-col items-center justify-center">
                <img src="<?= $book['image_url'] ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="w-48 h-64 object-cover rounded-lg">
                <p class="font-bold mt-2 text-center"><?= htmlspecialchars($book['title']) ?></p>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

                
            </div>
        </div>


            <!-- Show More Button -->
            <?php if (count($filteredBooks) > 8): ?>
                <div class="flex justify-end mt-6">
                    <a href="books.php" 
                        id="showMoreButton" 
                        class="text-blue-900 hover:text-blue-800 transition duration-300"
                    >
                        
                    </a>
                </div>
            <?php endif; ?>
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

<script>
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.querySelector("input[placeholder='Search your favorite books']");
    const searchResults = document.getElementById("searchResults");

    searchInput.addEventListener("input", function () {
        let query = this.value.trim();
        if (query.length > 1) {
            fetch(`books.php?search=${encodeURIComponent(query)}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById("searchResults").innerHTML = new DOMParser()
                        .parseFromString(html, "text/html")
                        .getElementById("searchResults").innerHTML;
                    searchResults.classList.remove("hidden");
                });
        } else {
            searchResults.classList.add("hidden");
        }
    });
});
</script>

</body>
</html>