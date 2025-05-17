
<?php
session_start();
include 'db.php'; // Include the database connection file

$uploads_dir = $_SERVER['DOCUMENT_ROOT'] . '/Uploads';
if (!is_dir($uploads_dir)) {
    if (!mkdir($uploads_dir, 0755, true)) {
        error_log("Failed to create Uploads directory: $uploads_dir");
    } else {
        error_log("Created Uploads directory: $uploads_dir");
    }
}

require_once 'get_unread_messages.php';
$unreadCount = getUnreadMessagesCount($conn, $_SESSION['user_id']);

// Get logged-in user ID
$user_id = $_SESSION['user_id'];

// Fetch logged-in user's profile picture
$sql = "SELECT profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_profile_pic = $user['profile_pic'] ? trim($user['profile_pic']) : './assets/055a91979264664a1ee12b9453610d82.jpg';
error_log("User $user_id profile_pic: $user_profile_pic");

$pendingRequestsCount = 0;
$sql = "SELECT COUNT(*) AS count 
        FROM requests 
        WHERE owner_id = ? AND status = 'Pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$pendingRequestsCount = $stmt->get_result()->fetch_assoc()['count'];

// Fetch the list of conversations (distinct users) ordered by the latest message timestamp
$query = "SELECT u.id, u.first_name, u.last_name, u.profile_pic, 
          MAX(m.created_at) as last_message_time,
          SUM(CASE WHEN m.receiver_id = $user_id AND (m.read_status = 0 OR m.is_read = 0) THEN 1 ELSE 0 END) as unread_count
          FROM messages m
          INNER JOIN users u ON u.id = CASE WHEN m.sender_id = $user_id THEN m.receiver_id ELSE m.sender_id END
          WHERE m.sender_id = $user_id OR m.receiver_id = $user_id
          GROUP BY u.id, u.first_name, u.last_name, u.profile_pic
          ORDER BY last_message_time DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query failed: " . mysqli_error($conn));  // Handle the case if query fails
}

// Check if the book owner is passed in the URL
$book_owner_id = $_GET['book_owner_id'] ?? null;

if ($book_owner_id) {
    // Mark messages as read when user opens the chat
    $updateReadStatus = "UPDATE messages 
                        SET read_status = 1, 
                            is_read = 1 
                        WHERE receiver_id = $user_id 
                        AND sender_id = $book_owner_id 
                        AND (read_status = 0 OR is_read = 0)";
    $conn->query($updateReadStatus);
    
    // Fetch messages between the logged-in user and the book owner
    $messages_query = "SELECT * FROM messages 
                       WHERE (sender_id = $user_id AND receiver_id = $book_owner_id) 
                          OR (sender_id = $book_owner_id AND receiver_id = $user_id)
                       ORDER BY created_at ASC";
    $messages_result = mysqli_query($conn, $messages_query);

    // Fetch the name and profile pic of the user you are chatting with
    $user_query = "SELECT first_name, last_name, profile_pic FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $book_owner_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $chat_user = $user_result->fetch_assoc();
    if ($chat_user && $chat_user['profile_pic']) {
        $chat_user['profile_pic'] = trim($chat_user['profile_pic']);
        error_log("Chat user $book_owner_id profile_pic: {$chat_user['profile_pic']}");
    } else {
        $chat_user['profile_pic'] = './assets/055a91979264664a1ee12b9453610d82.jpg';
        error_log("Chat user $book_owner_id using fallback profile_pic");
    }
} else {
    $messages_result = null; // No messages to display if no book_owner_id is set
    $chat_user = null;
}
$current_page = basename($_SERVER['PHP_SELF']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookLoop - Messages</title>
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

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: #c1c1c1 #f1f1f1;
            height: 100%;
            overflow-y: auto;
        }

        #chat-messages {
            scrollbar-width: thin;
            scrollbar-color: #c1c1c1 #f1f1f1;
            overflow-y: auto;
            max-height: calc(100vh - 280px);
        }

        #chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        #chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        #chat-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        #chat-messages::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        @media (max-width: 767px) {
            #chat-list.hidden {
                display: none;
            }
            #chat-window.hidden {
                display: none;
            }
            #chat-window {
                width: 100%;
            }
        }

        @media (min-width: 768px) {
            #chat-list, #chat-window {
                display: flex !important;
            }
        }

        /* Ensure message input form is responsive */
        .message-input-form {
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .message-input-form input {
            flex: 1;
            min-width: 0; /* Prevents input from overflowing */
            width: 100%;
        }

        .message-input-form button {
            flex-shrink: 0; /* Prevents button from shrinking */
        }

        /* Mobile-specific adjustments */
        @media (max-width: 767px) {
            .message-input-form {
                padding: 8px; /* Reduced padding for smaller screens */
            }
            
            .message-input-form input {
                padding: 8px 12px; /* Adjust padding for better fit */
                font-size: 14px; /* Slightly smaller font for mobile */
            }
            
            .message-input-form button {
                padding: 8px; /* Ensure button is compact */
            }
        }

        /* Adjust message spacing when no profile photo is present */
        .chat-message-container {
            display: flex;
            align-items: flex-end;
            margin-bottom: 16px;
        }

        .chat-message-container .message-bubble {
            max-width: 70%;
            padding: 8px 16px;
            border-radius: 16px;
            word-break: break-word;
        }

        .chat-message-container.justify-end .message-bubble {
            margin-left: 8px; /* Add some spacing when no profile photo */
        }

        @media (max-width: 767px) {
            .chat-message-container .message-bubble {
                max-width: 80%; /* Slightly wider messages on mobile */
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

    <main class="flex-1 p-4 md:p-6 overflow-x-hidden">
        <header class="hidden lg:flex items-center justify-between mb-6 bg-gradient-to-r from-purple-100 to-blue-900 text-white rounded-xl p-6 shadow-md">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold leading-snug text-blue-600">Keep books moving,</h1>
                <p class="text-base font-light -mt-1 text-blue-600">Keep stories alive.</p>
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

        <h2 class="text-2xl font-semibold mb-4">Messages</h2>
        <div class="flex flex-1 space-x-0 md:space-x-4 h-[75vh] overflow-hidden">
            <!-- Chat List -->
            <section id="chat-list" class="w-full md:w-80 bg-white rounded-xl shadow-lg <?= $book_owner_id ? 'hidden md:block' : '' ?>">
                <div class="overflow-y-auto h-[75vh] w-full custom-scrollbar">
                    <?php if (mysqli_num_rows($result) > 0): 
                        while ($row = mysqli_fetch_assoc($result)):
                            $chat_query = "SELECT * FROM messages 
                                          WHERE (sender_id = $user_id AND receiver_id = {$row['id']}) 
                                             OR (sender_id = {$row['id']} AND receiver_id = $user_id)
                                          ORDER BY created_at DESC LIMIT 1";
                            $chat_result = mysqli_query($conn, $chat_query);
                            $chat = mysqli_fetch_assoc($chat_result);
                            $chat_profile_pic = $row['profile_pic'] ? trim($row['profile_pic']) : './assets/055a91979264664a1ee12b9453610d82.jpg';
                            error_log("Chat list user {$row['id']} profile_pic: $chat_profile_pic");
                    ?>
                        <a href="message.php?book_owner_id=<?= $row['id'] ?>" 
                           class="flex items-center gap-3 p-4 hover:bg-gray-50 transition-colors border-b 
                           <?= isset($_GET['book_owner_id']) && $_GET['book_owner_id'] == $row['id'] ? 'bg-blue-50' : '' ?>
                           <?= $row['unread_count'] > 0 ? 'bg-blue-50 font-semibold' : '' ?>">
                            <div class="relative">
                                <img src="<?= htmlspecialchars($chat_profile_pic) ?>?t=<?= time() ?>" alt="User" 
                                     class="w-12 h-12 rounded-full border-2 <?= $row['unread_count'] > 0 ? 'border-blue-500' : 'border-gray-200' ?>">
                                <?php if ($row['unread_count'] > 0): ?>
                                    <span class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                        <?= $row['unread_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900 truncate"><?= $row['first_name'] . ' ' . $row['last_name'] ?></h4>
                                <p class="text-sm <?= $row['unread_count'] > 0 ? 'text-blue-600 font-medium' : 'text-gray-500' ?> truncate">
                                    <?= isset($chat['message']) ? substr($chat['message'], 0, 30) . (strlen($chat['message']) > 30 ? '...' : '') : 'No messages' ?>
                                </p>
                            </div>
                        </a>
                    <?php 
                        endwhile; 
                    else: ?>
                        <p class="p-4 text-gray-500">No conversations yet</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Chat Window -->
            <section id="chat-window" class="flex-1 bg-white rounded-xl shadow-lg flex flex-col <?= $book_owner_id ? '' : 'hidden md:flex' ?>">
                <?php if ($chat_user): ?>
                    <!-- Chat Header -->
                    <div class="p-4 border-b flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <button id="back-to-list" class="p-1 rounded-full hover:bg-gray-100 md:hidden">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"></path></svg>
                            </button>
                            <div class="relative">
                                <img src="<?= htmlspecialchars($chat_user['profile_pic']) ?>?t=<?= time() ?>" alt="User" 
                                     class="w-12 h-12 rounded-full border-2 border-gray-200">
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900"><?= $chat_user['first_name'] . ' ' . $chat_user['last_name'] ?></h4>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div class="flex-1 overflow-y-auto p-4 h-[calc(100vh-280px)] custom-scrollbar" id="chat-messages">
                        <?php if ($messages_result && mysqli_num_rows($messages_result) > 0):
                            while ($message = mysqli_fetch_assoc($messages_result)): ?>
                                <div class="chat-message-container flex <?= $message['sender_id'] == $user_id ? 'justify-end' : 'justify-start' ?>">
                                    <?php if ($message['sender_id'] != $user_id): ?>
                                        <img src="<?= htmlspecialchars($chat_user['profile_pic']) ?>?t=<?= time() ?>" alt="User" 
                                             class="w-8 h-8 rounded-full mr-2 self-end">
                                    <?php endif; ?>
                                    <div class="message-bubble <?= $message['sender_id'] == $user_id ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= htmlspecialchars($message['message']) ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center text-gray-500 mt-4">No messages yet. Start the conversation!</p>
                        <?php endif; ?>
                    </div>

                    <!-- Message Input -->
                    <div class="p-4 border-t">
                        <form action="send_message.php?book_owner_id=<?= $book_owner_id ?>" method="POST" 
                              class="message-input-form flex items-center gap-2 w-full">
                            <input type="text" name="message" placeholder="Type your message..." required
                                   class="flex-1 rounded-full px-4 py-2 border border-gray-300 focus:outline-none focus:border-blue-500 min-w-0">
                            <button type="submit" 
                                    class="bg-blue-500 text-white rounded-full p-2 hover:bg-blue-600 transition-colors flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                            <p class="text-xl">Select a conversation to start messaging</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
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

        // Chat Interface Scripts
        document.addEventListener('DOMContentLoaded', function() {
            // Back to chat list button (mobile)
            document.getElementById('back-to-list')?.addEventListener('click', function() {
                document.getElementById('chat-window').classList.add('hidden');
                document.getElementById('chat-list').classList.remove('hidden');
            });

            // Auto-scroll to bottom of messages
            const messagesDiv = document.getElementById('chat-messages');
            if (messagesDiv) {
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }

            // Function to load new messages and update chat head
            function loadMessages() {
                let bookOwnerId = new URLSearchParams(window.location.search).get("book_owner_id");
                if (!bookOwnerId) return;

                fetch(`fetch_messages.php?book_owner_id=${bookOwnerId}`)
                    .then(response => response.text())
                    .then(data => {
                        const messagesDiv = document.getElementById('chat-messages');
                        if (messagesDiv) {
                            messagesDiv.innerHTML = data;
                            messagesDiv.scrollTop = messagesDiv.scrollHeight;
                        }
                    });
            }

            // Call loadMessages() every 3 seconds
            setInterval(loadMessages, 3000);
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
