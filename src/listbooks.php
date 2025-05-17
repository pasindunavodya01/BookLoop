<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include the database connection
require_once 'db.php';

$user_id = $_SESSION['user_id'];

// Get unread messages count
require_once 'get_unread_messages.php';
$unreadCount = getUnreadMessagesCount($conn, $user_id);

$pendingRequestsCount = 0;
$sql = "SELECT COUNT(*) AS count FROM requests WHERE owner_id = ? AND status = 'Pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pendingRequestsCount = $stmt->get_result()->fetch_assoc()['count'];

// Initialize error variable
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $genre = isset($_POST['genre']) && is_array($_POST['genre']) ? implode(", ", $_POST['genre']) : '';
    $description = $_POST['description'];
    $availability_status = 'Available';
    $owner_id = $user_id;

    // Handle image upload
    $image_upload_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $image_name = $_FILES['image']['name'];
        $image_tmp_name = $_FILES['image']['tmp_name'];
        $image_ext = pathinfo($image_name, PATHINFO_EXTENSION);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array(strtolower($image_ext), $allowed_exts)) {
            $image_new_name = uniqid('', true) . "." . $image_ext;
            $image_upload_path = 'uploads/' . $image_new_name;

            if (!move_uploaded_file($image_tmp_name, $image_upload_path)) {
                $error = "Error uploading the image.";
            }
        } else {
            $error = "Only JPG, JPEG, PNG, and GIF files are allowed.";
        }
    } else {
        $error = "Please upload an image.";
    }

    // Insert book if no errors
    if (empty($error)) {
        $sql = "INSERT INTO books (title, author, genre, description, owner_id, availability_status, image_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiss", $title, $author, $genre, $description, $owner_id, $availability_status, $image_upload_path);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Book added successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "There was an error adding the book.";
        }
        $stmt->close();
    }
    
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

$conn->close();

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
    <main class="flex-1 p-6 overflow-x-hidden">
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

        <!-- Add Book Form -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-xl font-bold mb-8 text-gray-700">Add your Books</h3>
            <!-- Display success/error message -->
            <div id="error-message" class="<?= empty($error) ? 'hidden' : '' ?> text-red-500 mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php if (isset($_SESSION['success']) && !empty($_SESSION['success'])): ?>
                <p class="text-green-500"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" id="addBookForm">
                <div class="grid grid-cols-1 sm:grid-cols-6 gap-6">
                    <div class="col-span-1 sm:col-span-3">
                        <label class="font-bold text-gray-500 text-sm block mb-2">Title</label>
                        <input type="text" name="title" class="rounded-lg border px-3 py-2 mb-5 text-sm w-full outline-indigo-50" placeholder="Title" required>
                    </div>

                    <div class="col-span-1 sm:col-span-3">
                        <label class="font-bold text-gray-500 text-sm block mb-2">Author</label>
                        <input type="text" name="author" class="rounded-lg border px-3 py-2 mb-5 text-sm w-full outline-indigo-50" placeholder="Author" required>
                    </div>

                    <div class="col-span-1 sm:col-span-3">
                        <label class="font-bold text-gray-500 text-sm block mb-2">Genre</label>
                        <input type="text" name="genre[]" list="genre-list" class="rounded-lg border px-3 py-2 mb-5 text-sm w-full outline-indigo-50" placeholder="Type genre" multiple required>
                        <datalist id="genre-list">
                            <option value="Fiction">
                            <option value="Non-Fiction">
                            <option value="Science Fiction">
                            <option value="Fantasy">
                            <option value="History">
                            <option value="Business">
                            <option value="Self-Help">
                        </datalist>
                    </div>

                    <div class="col-span-1 sm:col-span-3">
                        <label class="font-bold text-gray-500 text-sm block mb-2">Description</label>
                        <textarea name="description" class="rounded-lg border px-3 py-2 mb-5 text-sm w-full outline-indigo-50" placeholder="Description" required></textarea>
                    </div>

                    <div class="col-span-1 sm:col-span-6">
                        <label for="image" class="block text-sm leading-6 text-gray-500 font-bold">Upload Image</label>
                        <div class="mt-2 flex justify-center rounded-lg border border-dashed border-gray-900/25 px-6 py-10">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                <div class="mt-4 flex text-sm leading-6 text-gray-600">
                                    <label for="file-upload" class="relative cursor-pointer rounded-md bg-white font-semibold text-indigo-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-600 focus-within:ring-offset-2 hover:text-indigo-500">
                                        <span>Upload a file</span>
                                        <input id="file-upload" name="image" type="file" class="sr-only" accept="image/jpeg,image/png,image/gif" required>
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs leading-5 text-gray-600">PNG, JPG, GIF up to 10MB</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-1 sm:col-span-6 flex justify-center mt-6">
                    <button type="submit" class="w-full sm:w-1/3 bg-blue-500 text-white p-2 rounded hover:bg-blue-700">
                        Add Book
                    </button>
                </div>
            </form>
        </div>
    </main>

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

        // Form Validation
        const addBookForm = document.getElementById('addBookForm');
        const imageInput = document.querySelector('input[name="image"]');
        const errorMessage = document.getElementById('error-message');

        if (addBookForm) {
            addBookForm.addEventListener('submit', function(event) {
                // Reset error message
                errorMessage.classList.add('hidden');
                
                // Client-side validation
                if (!imageInput.files.length) {
                    errorMessage.textContent = 'Please upload an image';
                    errorMessage.classList.remove('hidden');
                    event.preventDefault();
                    return;
                }

                // Log form data for debugging
                const formData = new FormData(addBookForm);
                console.log('Form submitted with data:', {
                    title: formData.get('title'),
                    author: formData.get('author'),
                    genres: formData.getAll('genre[]'),
                    description: formData.get('description'),
                    image: formData.get('image') ? 'Image selected' : 'No image'
                });
            });
        }

        // Clear error when file is selected
        if (imageInput) {
            imageInput.addEventListener('change', function() {
                errorMessage.classList.add('hidden');
            });
        }
    </script>
</body>
</html>