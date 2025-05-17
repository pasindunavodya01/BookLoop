<?php
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

// Handle Event Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_name'])) {
    $name = mysqli_real_escape_string($conn, $_POST['event_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);

    // Check if an image is uploaded
    if (empty($_FILES['image']['name'])) {
        echo json_encode(['status' => 'error', 'message' => 'An image is required for the event.']);
        exit();
    }

    $image_url = null;
    if (!empty($_FILES['image']['name'])) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $fileType = mime_content_type($_FILES['image']['tmp_name']);

        if (in_array($fileType, $allowedTypes)) {
            $image_url = 'Uploads/' . basename($_FILES['image']['name']);
            move_uploaded_file($_FILES['image']['tmp_name'], $image_url);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Only JPG, JPEG, PNG, and WebP files are allowed.']);
            exit();
        }
    }

    $insertQuery = "INSERT INTO events (user_id, event_name, description, event_date, event_time, venue, image_url, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("issssss", $user_id, $name, $description, $date, $time, $venue, $image_url);
    $stmt->execute();

    // Return JSON response for AJAX
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Event submitted for approval']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit event']);
    }
    exit();
}

// Handle participation toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['participate_event_id'])) {
    $event_id = intval($_POST['participate_event_id']);

    if ($user_id) {
        // Check if already participated
        $checkQuery = "SELECT * FROM event_participations WHERE event_id = ? AND user_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("ii", $event_id, $user_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            // Already participated, so remove participation
            $deleteQuery = "DELETE FROM event_participations WHERE event_id = ? AND user_id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("ii", $event_id, $user_id);
            $deleteStmt->execute();

            echo json_encode(['status' => 'removed']);
        } else {
            // Add participation
            $insertQuery = "INSERT INTO event_participations (event_id, user_id) VALUES (?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("ii", $event_id, $user_id);
            $insertStmt->execute();

            echo json_encode(['status' => 'added']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    }

    exit();
}

// Fetch approved events with user details
$approved_events_query = "SELECT e.*, u.first_name, u.last_name, u.email, u.profile_pic 
                         FROM events e 
                         JOIN users u ON e.user_id = u.id 
                         WHERE e.status = 'Approved'
                         ORDER BY e.event_date ASC, e.event_time ASC";
$approved_events_result = $conn->query($approved_events_query);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookLoop - Events</title>
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

        /* Align participation form to right on desktop */
        @media (min-width: 768px) {
            .participation-form {
                display: flex;
                justify-content: flex-end;
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
                <a href="account.php">
                    <img src="<?= htmlspecialchars($user_profile_pic) ?>?t=<?= time() ?>" alt="User" class="w-10 h-10 rounded-full border-2 border-white cursor-pointer object-cover">
                </a>
            </div>
        </header>

        <!-- Create Event Button -->
        <div class="mb-6">
            <button id="createEventBtn" class="px-6 py-3 bg-blue-900 text-white rounded-lg hover:bg-blue-800">
                Create an Event
            </button>
        </div>

        <!-- Event Form (Hidden by Default) -->
        <div id="eventForm" class="bg-white p-6 rounded-lg shadow-md max-w-3xl mx-auto mt-6 hidden">
            <h2 class="text-xl font-semibold mb-4">Submit a New Event</h2>
            <form id="eventSubmissionForm" method="POST" enctype="multipart/form-data">
                <input type="text" name="event_name" placeholder="Event Name" class="w-full p-3 mb-4 border rounded" required>
                <textarea name="description" placeholder="Description" class="w-full p-3 mb-4 border rounded" required></textarea>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <input type="date" name="date" class="p-3 border rounded" required>
                    <input type="time" name="time" class="p-3 border rounded" required>
                </div>
                <input type="text" name="venue" placeholder="Venue" class="w-full p-3 mb-4 border rounded" required>
                <input type="file" name="image" accept="image/jpeg,image/jpg,image/png,image/webp" class="mb-4" required>
                <button type="submit" class="bg-blue-900 text-white px-6 py-3 rounded hover:bg-blue-800">Submit Event</button>
            </form>
            <div id="formResponse" class="mt-4 hidden"></div>
        </div>

        <!-- Approved Events Section -->
        <div class="mt-8">
            <h2 class="text-2xl font-bold mb-6 text-blue-900 text-center">Upcoming Events</h2>

            <!-- Wrapper to control width and center the content -->
            <div class="max-w-6xl mx-auto px-4">
                <div id="eventsContainer">
                    <?php if ($approved_events_result->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php while ($event = $approved_events_result->fetch_assoc()):
                                $event_date = new DateTime($event['event_date']);
                                $current_date = new DateTime();
                                $is_upcoming = $event_date >= $current_date;
                            ?>
                                <div class="event-card bg-white rounded-lg shadow-md p-4 mb-6">
                                    <!-- User Info -->
                                    <div class="flex items-center mb-4">
                                        <img src="<?php echo htmlspecialchars($event['profile_pic'] ?? './assets/default-profile.jpg'); ?>" alt="User" class="w-10 h-10 rounded-full mr-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($event['first_name']) . ' ' . htmlspecialchars($event['last_name']); ?>
                                            </p>
                                            <p class="text-xs text-gray-400">
                                                <?php echo date('F j, Y \a\t g:i A', strtotime($event['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Event Details -->
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($event['event_name']); ?></h3>
                                        <p class="text-sm text-gray-700 mb-3"><?php echo htmlspecialchars($event['description']); ?></p>
                                        <br>

                                        <div class="flex items-center text-sm text-gray-500 mb-1">
                                            <i class="fas fa-calendar-day mr-2"></i>
                                            <span><?php echo $event_date->format('F j, Y'); ?></span>
                                        </div>

                                        <div class="flex items-center text-sm text-gray-500 mb-1">
                                            <i class="fas fa-clock mr-2"></i>
                                            <span><?php echo date('h:i A', strtotime($event['event_time'])); ?></span>
                                        </div>

                                        <div class="flex items-center text-sm text-gray-500 mb-3">
                                            <i class="fas fa-location-dot mr-2"></i>
                                            <span><?php echo htmlspecialchars($event['venue']); ?></span>
                                        </div>

                                        <!-- Status -->
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold 
                                            <?php echo $is_upcoming ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo $is_upcoming ? 'Upcoming' : 'Past Event'; ?>
                                        </span>
                                    </div>

                                    <!-- Event Image -->
                                    <?php if (!empty($event['image_url'])): ?>
                                        <div class="w-full flex justify-center mb-4">
                                            <img src="<?php echo htmlspecialchars($event['image_url']); ?>" alt="Event Image" class="max-w-xs w-full object-contain rounded-md">
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full flex justify-center mb-4">
                                            <div class="max-w-xs w-full aspect-square bg-gray-200 flex items-center justify-center rounded-md">
                                                <i class="fas fa-calendar-day text-4xl text-gray-400"></i>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Participation -->
                                    <?php
                                    // Count participants
                                    $count_query = "SELECT COUNT(*) as total FROM event_participations WHERE event_id = ?";
                                    $count_stmt = $conn->prepare($count_query);
                                    $count_stmt->bind_param("i", $event['event_id']);
                                    $count_stmt->execute();
                                    $count_result = $count_stmt->get_result();
                                    $participant_count = $count_result->fetch_assoc()['total'];

                                    // Check if current user has participated
                                    $has_participated = false;
                                    if ($user_id) {
                                        $check_query = "SELECT 1 FROM event_participations WHERE event_id = ? AND user_id = ?";
                                        $check_stmt = $conn->prepare($check_query);
                                        $check_stmt->bind_param("ii", $event['event_id'], $user_id);
                                        $check_stmt->execute();
                                        $check_result = $check_stmt->get_result();
                                        $has_participated = $check_result->num_rows > 0;
                                    }
                                    ?>
                                    <form class="participation-form mt-2" onsubmit="toggleParticipation(event, <?php echo $event['event_id']; ?>)">
                                        <div class="flex items-center space-x-4">
                                            <button type="submit"
                                                class="<?php echo $has_participated ? 'bg-red-500 text-white' : 'bg-blue-900 text-white'; ?> px-4 py-2 rounded font-bold transition-opacity duration-300"
                                                onmouseover="this.style.opacity='0.9';" 
                                                onmouseout="this.style.opacity='1';">
                                                <?php echo $has_participated ? 'Withdraw Participation' : 'Participate'; ?>
                                            </button>
                                            <p class="text-sm text-gray-600">
                                                Participants: 
                                                <span id="participant-count-<?php echo $event['event_id']; ?>">
                                                    <?php echo $participant_count; ?>
                                                </span>
                                            </p>
                                        </div>
                                    </form>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-lg shadow-md p-6 text-center">
                            <i class="fas fa-calendar-day text-5xl text-gray-400 mb-4"></i>
                            <h3 class="text-xl font-semibold mb-2">No Events Available</h3>
                            <p class="text-gray-600">There are currently no approved events. Check back later or create your own event!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

        // Event Form and Participation Scripts
        $(document).ready(function() {
            // Toggle event form visibility
            $('#createEventBtn').click(function() {
                $('#eventForm').toggleClass('hidden');
            });

            // Handle form submission with AJAX
            $('#eventSubmissionForm').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            var data = JSON.parse(response);
                            if (data.status === 'success') {
                                $('#formResponse').removeClass('hidden bg-red-100 text-red-800')
                                    .addClass('bg-green-100 text-green-800')
                                    .text(data.message);
                                
                                // Clear form
                                $('#eventSubmissionForm')[0].reset();
                                
                                // Hide form after 3 seconds
                                setTimeout(function() {
                                    $('#eventForm').addClass('hidden');
                                    $('#formResponse').addClass('hidden');
                                }, 3000);
                            } else {
                                $('#formResponse').removeClass('hidden bg-green-100 text-green-800')
                                    .addClass('bg-red-100 text-red-800')
                                    .text(data.message);
                            }
                        } catch (e) {
                            $('#formResponse').removeClass('hidden bg-green-100 text-green-800')
                                .addClass('bg-red-100 text-red-800')
                                .text('Error processing response');
                        }
                    },
                    error: function() {
                        $('#formResponse').removeClass('hidden bg-green-100 text-green-800')
                            .addClass('bg-red-100 text-red-800')
                            .text('Error submitting form');
                    }
                });
            });

            // Function to check for new events
            function checkForNewEvents() {
                $.ajax({
                    url: window.location.href,
                    type: 'GET',
                    data: { check_events: true },
                    success: function(response) {
                        $('#eventsContainer').load(window.location.href + ' #eventsContainer > *');
                    }
                });
            }

            // Check for new events every 30 seconds
            setInterval(checkForNewEvents, 30000);
        });

        function toggleParticipation(e, eventId) {
            e.preventDefault();

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: { participate_event_id: eventId },
                success: function(response) {
                    try {
                        let data = JSON.parse(response);
                        if (data.status === 'added' || data.status === 'removed') {
                            // Reload just the event container
                            $('#eventsContainer').load(window.location.href + ' #eventsContainer > *');
                        }
                    } catch (err) {
                        console.error('Invalid response');
                    }
                }
            });
        }
    </script>
</body>
</html>