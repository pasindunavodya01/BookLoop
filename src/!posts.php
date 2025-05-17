<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'get_unread_messages.php';
$unreadCount = getUnreadMessagesCount($conn, $_SESSION['user_id']);

$pendingRequestsCount = 0;
$sql = "SELECT COUNT(*) AS count FROM requests WHERE owner_id = ? AND status = 'Pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$pendingRequestsCount = $stmt->get_result()->fetch_assoc()['count'];

$postsQuery = "
    SELECT p.post_id, p.content, p.image_url, p.created_at, 
           CONCAT(u.first_name, ' ', u.last_name) AS username, 
           u.profile_pic, 
           (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) AS likes_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) AS comments_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
";

$searchQuery = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
if ($searchQuery) {
    $postsQuery = "
        SELECT p.post_id, p.content, p.image_url, p.created_at, 
               CONCAT(u.first_name, ' ', u.last_name) AS username, 
               u.profile_pic, 
               (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) AS likes_count,
               (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) AS comments_count
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.content LIKE '%$searchQuery%'
        ORDER BY p.created_at DESC
    ";
}
$postsResult = $conn->query($postsQuery);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $user_id = $_SESSION['user_id'];
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $image_url = isset($_FILES['image']) && $_FILES['image']['name'] ? 'Uploads/' . $_FILES['image']['name'] : NULL;

    if ($image_url) {
        move_uploaded_file($_FILES['image']['tmp_name'], $image_url);
    }

    $insertPostQuery = "INSERT INTO posts (user_id, content, image_url) VALUES ($user_id, '$content', '$image_url')";
    if ($conn->query($insertPostQuery)) {
        header("Location: posts.php");
        exit();
    }
}

if (isset($_GET['like_post_id'])) {
    $post_id = $_GET['like_post_id'];
    $user_id = $_SESSION['user_id'];

    $checkLikeQuery = "SELECT * FROM likes WHERE post_id = $post_id AND user_id = $user_id";
    $likeResult = $conn->query($checkLikeQuery);

    if ($likeResult->num_rows == 0) {
        $likeQuery = "INSERT INTO likes (post_id, user_id) VALUES ($post_id, $user_id)";
        $conn->query($likeQuery);
    }
    header("Location: posts.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_profile_pic = $user['profile_pic'] ?: './assets/055a91979264664a1ee12b9453610d82.jpg';

if (isset($_POST['comment_content'], $_POST['post_id'])) {
    $user_id = $_SESSION['user_id'];
    $content = mysqli_real_escape_string($conn, $_POST['comment_content']);
    $post_id = $_POST['post_id'];

    $insertCommentQuery = "INSERT INTO comments (post_id, user_id, content) VALUES ($post_id, $user_id, '$content')";
    $conn->query($insertCommentQuery);
    header("Location: posts.php");
    exit();
}
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
                display: none;
            }

            body.lg\:flex-row {
                flex-direction: row;
            }

            header.lg\:flex {
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
            width: 100%;
        }

        .comments-container {
            height: auto;
        }

        .comment-list > div:nth-child(n+3) {
            display: none;
        }

        .comment-list.show-all > div {
            display: flex;
        }

        .see-more-comments {
            display: block;
            margin: 0 auto;
            padding: 0.5rem 1rem;
            background-color: #3b82f6;
            color: white;
            border-radius: 0.375rem;
            cursor: pointer;
            text-align: center;
            font-size: 0.875rem;
        }

        .see-more-comments:hover {
            background-color: #2563eb;
        }

        @media (min-width: 768px) {
            .post-container {
                flex-direction: row;
                align-items: flex-start;
                gap: 1.5rem;
            }

            .comments-wrapper {
                flex: 1;
                width: 20rem;
            }

            .comments-container {
                height: 24rem;
            }

            .comment-list > div {
                display: flex !important;
            }

            .see-more-comments {
                display: none;
            }
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
                <li><a href="events.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-calendar-days mr-3" style="color: #74C0FC;"></i> Events</a></li>
                <li>
                    <a href="posts.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 bg-blue-100">
                        <i class="fa-solid fa-image mr-3" style="color: #74C0FC;"></i> Posts
                    </a>
                </li>
                <li><a href="support.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-ticket mr-3" style="color: #74C0FC;"></i> Support</a></li>
                <li><a href="account.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-user mr-3" style="color: #74C0FC;"></i> Profile</a></li>
                <li><a href="logout.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300"><i class="fa-solid fa-right-from-bracket mr-3" style="color: #74C0FC;"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <div id="sidebarOverlay" class="sidebar-overlay hidden"></div>

    <main class="flex-1 p-6 overflow-x-hidden">
        <header class="hidden lg:flex items-center justify-between mb-6 bg-gradient-to-r from-purple-100 to-blue-900 text-white rounded-xl p-6 shadow-md">
            <div class="flex items-center gap-4">
                <form action="posts.php" method="GET" class="flex items-center gap-4">
                    <input 
                        type="text" 
                        name="search" 
                        class="p-3 rounded-full border border-gray-300 w-96 text-gray-800" 
                        placeholder="Search posts..." 
                        value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
                    >
                    <button type="submit" class="px-6 py-3 bg-blue-900 text-white rounded-lg">
                        Search
                    </button>
                </form>
            </div>
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
                    <img src="<?= htmlspecialchars($user_profile_pic) ?>?t=<?= time() ?>" alt="User" class="w-10 h-10 rounded-full border-2 border-white cursor-pointer object-cover">
                </a>
            </div>
        </header>

        <div class="p-6">
            <button id="createPostButton" class="px-6 py-3 bg-blue-900 text-white rounded-lg mb-4">
                Create Post
            </button>

            <div id="createPostForm" class="p-6 hidden">
                <h2 class="text-2xl font-bold mb-4">Create a Post</h2>
                <form action="posts.php" method="POST" enctype="multipart/form-data">
                    <textarea name="content" class="w-full p-3 mb-4 rounded-lg border" placeholder="What's on your mind?" required></textarea>
                    <input type="file" name="image" class="mb-4">
                    <button type="submit" class="px-6 py-3 bg-blue-900 text-white rounded-lg">Post</button>
                </form>
            </div>

            <h2 class="text-2xl font-bold mb-4">Latest Posts</h2>
            <?php if ($postsResult->num_rows > 0): ?>
                <?php while ($post = $postsResult->fetch_assoc()): ?>
                    <div class="post-container bg-white p-6 mb-6 rounded-lg shadow-md">
                        <div>
                            <div class="flex items-center mb-4">
                                <img src="<?= $post['profile_pic'] ?>" alt="Profile" class="w-12 h-12 rounded-full mr-3">
                                <div>
                                    <p class="font-bold"><?= htmlspecialchars($post['username']) ?></p>
                                    <p class="text-gray-500 text-sm"><?= date('F j, Y, g:i a', strtotime($post['created_at'])) ?></p>
                                </div>
                            </div>
                            <p class="mb-4"><?= htmlspecialchars($post['content']) ?></p>
                            <?php if ($post['image_url']): ?>
                                <img src="<?= $post['image_url'] ?>" alt="Post Image" class="w-full max-w-2xl mx-auto max-h-80 object-contain rounded-lg">
                            <?php endif; ?>
                        </div>
                        <div class="comments-wrapper">
                            <div class="mb-4 flex items-center">
                                <a href="posts.php?like_post_id=<?= $post['post_id'] ?>" class="mr-4">
                                    ❤️ <?= $post['likes_count'] ?> Likes
                                </a>
                                <a href="#comments-<?= $post['post_id'] ?>" class="mr-4">
                                    💬 <?= $post['comments_count'] ?> Comments
                                </a>
                            </div>
                            <div id="comments-<?= $post['post_id'] ?>" class="comments-container overflow-y-auto">
                                <form action="posts.php" method="POST" class="mb-4">
                                    <textarea name="comment_content" class="w-full p-3 mb-4 rounded-lg border" placeholder="Add a comment" required></textarea>
                                    <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                    <button type="submit" class="px-6 py-3 bg-yellow-500 text-white rounded-lg">Comment</button>
                                </form>
                                <div class="comment-list">
                                    <?php
                                    $commentsQuery = "SELECT c.content, c.created_at, u.first_name, u.last_name, u.profile_pic 
                                                     FROM comments c 
                                                     JOIN users u ON c.user_id = u.id 
                                                     WHERE c.post_id = " . $post['post_id'] . "
                                                     ORDER BY c.created_at DESC";
                                    $commentsResult = $conn->query($commentsQuery);
                                    while ($comment = $commentsResult->fetch_assoc()):
                                    ?>
                                        <div class="flex items-start mb-4">
                                            <img src="<?= $comment['profile_pic'] ?>" alt="Profile" class="w-8 h-8 rounded-full mr-3">
                                            <div>
                                                <p class="font-bold"><?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?></p>
                                                <p class="text-sm text-gray-500"><?= date('F j, Y, g:i a', strtotime($comment['created_at'])) ?></p>
                                                <p class="mt-2"><?= htmlspecialchars($comment['content']) ?></p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <?php if ($commentsResult->num_rows > 2): ?>
                                    <button class="see-more-comments">See More</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-gray-500">No posts found.</p>
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

            console.log('Sidebar script initialized. Checking elements:', {
                hamburgerBtn: !!hamburgerBtn,
                sidebar: !!sidebar,
                sidebarOverlay: !!sidebarOverlay,
                closeSidebarBtn: !!closeSidebarBtn
            });

            if (!hamburgerBtn || !sidebar || !sidebarOverlay || !closeSidebarBtn) {
                console.error('Sidebar elements missing');
                throw new Error('Required sidebar elements not found');
            }

            function openSidebar() {
                console.log('Opening sidebar');
                sidebar.classList.add('sidebar-open');
                sidebarOverlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                console.log('Closing sidebar');
                sidebar.classList.remove('sidebar-open');
                sidebarOverlay.classList.add('hidden');
                document.body.style.overflow = '';
            }

            hamburgerBtn.addEventListener('click', function(e) {
                console.log('Hamburger button clicked', e);
                if (sidebar.classList.contains('sidebar-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });

            closeSidebarBtn.addEventListener('click', function(e) {
                console.log('Close button clicked', e);
                closeSidebar();
            });

            sidebarOverlay.addEventListener('click', function(e) {
                console.log('Overlay clicked', e);
                closeSidebar();
            });

            sidebar.querySelectorAll('nav a').forEach(link => {
                link.addEventListener('click', function(e) {
                    console.log('Sidebar link clicked:', link.href);
                    closeSidebar();
                });
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    console.log('Window resized to desktop, closing sidebar');
                    closeSidebar();
                }
            });
        } catch (error) {
            console.error('Sidebar initialization error:', error);
        }

        // Create Post Form Toggle
        try {
            const createPostButton = document.getElementById('createPostButton');
            const createPostForm = document.getElementById('createPostForm');

            console.log('Create post script initialized. Checking elements:', {
                createPostButton: !!createPostButton,
                createPostForm: !!createPostForm
            });

            if (!createPostButton || !createPostForm) {
                console.error('Create post elements missing');
                throw new Error('Required create post elements not found');
            }

            createPostButton.addEventListener('click', function() {
                console.log('Create post button clicked');
                if (createPostForm.classList.contains('hidden')) {
                    createPostForm.classList.remove('hidden');
                } else {
                    createPostForm.classList.add('hidden');
                }
            });
        } catch (error) {
            console.error('Create post initialization error:', error);
        }

        // See More Comments Toggle
        try {
            const seeMoreButtons = document.querySelectorAll('.see-more-comments');

            console.log('See more comments script initialized. Found buttons:', seeMoreButtons.length);

            seeMoreButtons.forEach(button => {
                button.addEventListener('click', function() {
                    console.log('See more comments button clicked');
                    const commentList = button.previousElementSibling;
                    if (commentList.classList.contains('show-all')) {
                        commentList.classList.remove('show-all');
                        button.textContent = 'See More';
                    } else {
                        commentList.classList.add('show-all');
                        button.textContent = 'Show Less';
                    }
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