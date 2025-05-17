<?php
require '../vendor/autoload.php'; // Composer's autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

// Fetch logged-in user's profile picture
$user_profile_pic = './assets/055a91979264664a1ee12b9453610d82.jpg'; // Default
if ($user_id) {
    $sql = "SELECT profile_pic FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_profile_pic = $user['profile_pic'] ? trim($user['profile_pic']) : './assets/055a91979264664a1ee12b9453610d82.jpg';
    error_log("User $user_id profile_pic: $user_profile_pic");
}

// Fetch unread messages count
$unreadCount = 0;
if ($user_id) {
    require_once 'get_unread_messages.php';
    $unreadCount = getUnreadMessagesCount($conn, $user_id);
}

// Fetch pending requests count
$pendingRequestsCount = 0;
if ($user_id) {
    $sql = "SELECT COUNT(*) AS count FROM requests WHERE owner_id = ? AND status = 'Pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pendingRequestsCount = $stmt->get_result()->fetch_assoc()['count'];
}

function sendStatusEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'Networksys20@gmail.com'; // your email
        $mail->Password = 'jpav jwsk fyjc mjso';    // your app password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('Networksys20@gmail.com', 'BookLoop Admin');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventId = $_POST['event_id'];
    $action = $_POST['status'];

    $emailQuery = $conn->prepare("SELECT u.email, e.event_name FROM events e JOIN users u ON e.user_id = u.id WHERE e.event_id = ?");
    $emailQuery->bind_param("i", $eventId);
    $emailQuery->execute();
    $emailResult = $emailQuery->get_result();
    $userData = $emailResult->fetch_assoc();
    $userEmail = $userData['email'];
    $eventName = $userData['event_name'];

    if ($action === 'Approved') {
        $stmt = $conn->prepare("UPDATE events SET status = 'Approved' WHERE event_id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();

        $subject = "Your Event '$eventName' Has Been Approved!";
        $body = "<p>Hello,</p><p>Your event <strong>$eventName</strong> has been approved and is now live on BookLoop.</p>";
        sendStatusEmail($userEmail, $subject, $body);

    } elseif ($action === 'Rejected') {
        $reason = $_POST['rejection_reason'] ?? '';
        $stmt = $conn->prepare("UPDATE events SET status = 'Rejected', rejection_reason = ? WHERE event_id = ?");
        $stmt->bind_param("si", $reason, $eventId);
        $stmt->execute();

        $subject = "Your Event '$eventName' Was Rejected";
        $body = "<p>Hello,</p><p>Your event <strong>$eventName</strong> was rejected for the following reason:</p><p><em>$reason</em></p>";
        sendStatusEmail($userEmail, $subject, $body);
    }

    $current_page = basename($_SERVER['PHP_SELF']);

    header("Location: admin_dashboard.php");
    exit;
}

$query = "
    SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) AS username
    FROM events e
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'Pending'
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | BookLoop</title>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        /* Ensure modal is centered */
        #rejection-modal {
            display: none;
        }

        #rejection-modal.flex {
            display: flex;
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
        <div class="w-full mt-4">
            <a href="admin_tickets.php" class="block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-center">
                <i class="fa-solid fa-ticket-alt mr-2" style="color: #ffffff;"></i>View User Inquiries
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
                <li><a href="admin_dashboard.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'admin_dashboard.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-house mr-3" style="color: #74C0FC;"></i>Dashboard</a></li>
                <li><a href="admin_tickets.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'admin_tickets.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-house mr-3" style="color: #74C0FC;"></i>User Inquiries</a></li>
               
                <li><a href="books.php" class="block p-3 text-gray-600 hover:bg-blue-900 hover:text-white rounded transition duration-300 <?= $current_page === 'books.php' ? 'bg-blue-100' : '' ?>"><i class="fa-solid fa-book mr-3" style="color: #74C0FC;"></i> Books</a></li>
                
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

    <!-- Main Content -->
    <main class="flex-1 p-4 md:p-6 overflow-x-hidden">
        <!-- Desktop Header -->
        <header class="hidden lg:flex items-center justify-between mb-6 bg-gradient-to-r from-purple-100 to-blue-900 text-white rounded-xl p-6 shadow-md">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold leading-snug text-blue-600">Keep books moving,</h1>
                <p class="text-base font-light -mt-1 text-blue-600">Keep stories alive.</p>
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
                <a href="admin_tickets.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa-solid fa-ticket-alt mr-2" style="color: #ffffff;"></i>View User Inquiries
                </a>
                <a href="account.php">
                    <img src="<?= htmlspecialchars($user_profile_pic) ?>?t=<?= time() ?>" alt="User" class="w-10 h-10 rounded-full border-2 border-white cursor-pointer object-cover">
                </a>
            </div>
        </header>

        <h1 class="text-3xl font-bold text-blue-900 mb-6">Pending Event Requests</h1>

        <div id="event-list" class="space-y-4">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($event = $result->fetch_assoc()): ?>
                    <div class="bg-white p-4 rounded-lg shadow-lg flex gap-4 items-center" data-event-id="<?= $event['event_id'] ?>">
                        <?php if (!empty($event['image_url'])): ?>
                            <img src="<?= htmlspecialchars($event['image_url']) ?>" alt="Event Image" class="w-20 h-20 object-cover rounded-lg">
                        <?php else: ?>
                            <div class="w-20 h-20 bg-gray-200 flex items-center justify-center rounded-lg text-sm text-gray-500">No Image</div>
                        <?php endif; ?>

                        <div class="flex-1">
                            <h3 class="text-xl font-semibold"><?= htmlspecialchars($event['event_name']) ?></h3>
                            <p class="text-gray-700"><?= htmlspecialchars($event['description']) ?></p>
                            <p class="text-gray-500"><strong>Date:</strong> <?= $event['event_date'] ?> <strong>Time:</strong> <?= $event['event_time'] ?></p>
                            <p class="text-gray-500"><strong>Venue:</strong> <?= htmlspecialchars($event['venue']) ?></p>
                            <p class="text-gray-500"><strong>Submitted by:</strong> <?= htmlspecialchars($event['username']) ?></p>
                        </div>

                        <form method="POST" class="space-x-2 flex items-center">
                            <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                            <button
                                type="submit"
                                name="status"
                                value="Approved"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                Approve
                            </button>
                            <button
                                type="button"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 open-reject-modal"
                                data-event-id="<?= $event['event_id'] ?>">
                                Reject
                            </button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-gray-500">No pending events to review.</p>
            <?php endif; ?>
        </div>

        <!-- Rejection Modal -->
        <div id="rejection-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
            <form method="POST" class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md space-y-4">
                <h2 class="text-xl font-bold text-gray-800">Reason for Rejection</h2>
                <input type="hidden" name="event_id" id="modal-event-id">
                <input type="hidden" name="status" value="Rejected">
                <textarea name="rejection_reason" id="modal-reason" class="w-full p-2 border border-gray-300 rounded" rows="4" required></textarea>
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancel-modal" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Reject</button>
                </div>
            </form>
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

        // Event Approval/Rejection Scripts
        const modal = document.getElementById('rejection-modal');
        const modalEventId = document.getElementById('modal-event-id');
        const modalReason = document.getElementById('modal-reason');
        const cancelBtn = document.getElementById('cancel-modal');

        function openModalHandler(e) {
            const eventId = e.currentTarget.getAttribute('data-event-id');
            modalEventId.value = eventId;
            modalReason.value = '';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        document.querySelectorAll('.open-reject-modal').forEach(button => {
            button.addEventListener('click', openModalHandler);
        });

        cancelBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });

        function fetchPendingEvents() {
            const existingIds = Array.from(document.querySelectorAll('[data-event-id]'))
                .map(div => div.getAttribute('data-event-id'));

            $.ajax({
                url: 'fetch_pending_events.php',
                type: 'GET',
                data: { existing_ids: existingIds.join(',') },
                success: function (data) {
                    if (data.trim() !== '') {
                        const noEventsMsg = document.querySelector('#event-list p.text-gray-500');
                        if (noEventsMsg) {
                            noEventsMsg.remove();
                        }

                        $('#event-list').append(data);

                        document.querySelectorAll('.open-reject-modal').forEach(button => {
                            button.removeEventListener('click', openModalHandler);
                            button.addEventListener('click', openModalHandler);
                        });
                    }
                }
            });
        }

        setInterval(fetchPendingEvents, 10000);
    </script>
</body>
</html>