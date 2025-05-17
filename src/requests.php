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

$user_id = $_SESSION['user_id'];
$sql = "SELECT r.request_id, b.title, b.image_url, u.first_name, u.last_name, r.status, r.requester_id 
        FROM requests r
        JOIN books b ON r.book_id = b.book_id
        JOIN users u ON r.requester_id = u.id
        WHERE r.owner_id = ? AND r.status != 'Rejected'
        ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sql = "SELECT profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_profile_pic = $user['profile_pic'] ?: './assets/055a91979264664a1ee12b9453610d82.jpg';

$current_page = basename($_SERVER['PHP_SELF']);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookLoop - Requests</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="output.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                    <?php if ($pendingRequestsCount > 0): ?>
                        <span class="notification-badge absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center notification-count">
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

    <main class="flex-1 p-6 overflow-x-hidden">
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
                            <span class="notification-badge absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center notification-count">
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

        <div id="successAlert" class="hidden items-center p-4 mb-4 bg-green-100 text-green-700 rounded-lg"></div>

        <div class="max-w-4xl mx-auto bg-white p-6 rounded-lg shadow-lg">
            <h1 class="text-2xl font-bold text-blue-700 mb-6">Book Requests</h1>
            <?php if (!empty($requests)): ?>
                <div class="space-y-4">
                    <?php foreach ($requests as $request): ?>
                        <a href="user_reviews.php?user_id=<?= $request['requester_id'] ?>" class="block hover:bg-gray-100 rounded-lg transition">
                            <div class="request-card flex flex-col sm:flex-row items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4 w-full sm:w-auto">
                                    <img src="<?= htmlspecialchars($request['image_url']) ?>" 
                                         alt="Book Cover" 
                                         class="w-16 h-24 sm:w-20 sm:h-28 object-cover rounded-md">
                                         <div class="text-center sm:text-left">
    <h3 class="font-semibold text-base sm:text-lg">
        <?= htmlspecialchars($request['title']) ?>
    </h3>
    <p class="text-gray-600 text-sm sm:text-base">
        Requested by: 
        <span class="text-blue-600 font-medium">
            <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?>
        </span>
    </p>
</div>

                                </div>
                                <form method="POST" class="request-form flex space-x-2 mt-4 sm:mt-0">
                                    <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                    <div class="action-buttons">
                                        <?php if ($request['status'] === 'Pending'): ?>
                                            <button type="submit" 
                                                    name="action" 
                                                    value="accept" 
                                                    class="inline-flex items-center px-3 py-1 sm:px-4 sm:py-2 bg-blue-500 mr-2 text-white rounded-md hover:bg-green-600 transition-colors text-sm sm:text-base">
                                                <span>Accept</span>
                                            </button>
                                            <button type="submit" 
                                                    name="action" 
                                                    value="reject" 
                                                    class="inline-flex items-center px-3 py-1 sm:px-4 sm:py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors text-sm sm:text-base">
                                                <span>Reject</span>
                                            </button>
                                        <?php elseif (in_array($request['status'], ['Accepted', 'Picked Up', 'Returned'])): ?>
                                            <span class="px-3 py-1 sm:px-4 sm:py-2 bg-green-200 text-green-700 rounded-md text-sm sm:text-base">Accepted</span>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-8">No pending requests found.</p>
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

        // jQuery AJAX for Request Actions
        jQuery(document).ready(function($) {
            $(document).on('click', '.request-form button[type="submit"]', function() {
                console.log('Button clicked:', $(this).val());
                $(this).attr('clicked', 'true');
            });

            $(document).on('submit', '.request-form', function(e) {
                e.preventDefault();
                const form = $(this);
                const requestCard = form.closest('.request-card');
                
                const action = form.find('button[type="submit"][clicked]').val();
                const requestId = form.find('input[name="request_id"]').val();
                
                console.log('Submitting request:', { request_id: requestId, action: action });

                $.ajax({
                    url: 'handle_request.php',
                    type: 'POST',
                    data: {
                        request_id: requestId,
                        action: action
                    },
                    success: function(response) {
                        console.log('AJAX Success:', response);
                        try {
                            const data = JSON.parse(response);
                            if (data.success) {
                                if (action === 'accept') {
                                    requestCard.find('.action-buttons').html(
                                        '<span class="px-3 py-1 sm:px-4 sm:py-2 bg-green-200 text-green-700 rounded-md text-sm sm:text-base">Accepted</span>'
                                    );
                                } else {
                                    requestCard.fadeOut(400, function() {
                                        $(this).remove();
                                    });
                                }
                                
                                $('#successAlert').text(data.message)
                                    .removeClass('hidden bg-red-100 text-red-700')
                                    .addClass('flex bg-green-100 text-green-700');
                                
                                const countElement = $('.notification-count');
                                let count = parseInt(countElement.text()) || 0;
                                count = Math.max(0, count - 1);
                                countElement.text(count);
                                if (count === 0) {
                                    $('.notification-badge').addClass('hidden');
                                }
                                console.log('Updated notification count:', count);
                            } else {
                                $('#successAlert').text(data.message || 'Action failed')
                                    .removeClass('hidden bg-green-100 text-green-700')
                                    .addClass('flex bg-red-100 text-red-700');
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e, response);
                            $('#successAlert').text('Error processing response')
                                .removeClass('hidden bg-green-100 text-green-700')
                                .addClass('flex bg-red-100 text-red-700');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error, xhr.responseText);
                        $('#successAlert').text('Error: ' + error)
                            .removeClass('hidden bg-green-100 text-green-700')
                            .addClass('flex bg-red-100 text-red-700');
                    },
                    complete: function() {
                        console.log('AJAX request completed');
                        form.find('button[type="submit"]').removeAttr('clicked');
                    }
                });
            });
        });
    </script>
</body>
</html>