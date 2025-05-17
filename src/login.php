<?php
include 'db.php'; // Include the database connection file


$error = ""; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['Email'];
    $password = $_POST['password'];

    $sql = "SELECT id, email, password, role FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $db_email, $db_password, $role);
        $stmt->fetch();

        if (password_verify($password, $db_password)) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_email'] = $db_email;
            $_SESSION['loggedIn'] = true; 

            if ($role === 'admin') {
                header("Location: admin_dashboard.php"); 
                exit();
            } else {
                header("Location: home.php"); 
                exit();
            }
        } else {
            $error = "Invalid email or password!";
        }
    } else {
        // Email not found in the database
        $error = "Invalid email or password!";
    }

    $stmt->close();
}

$conn->close();
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
<body class="font-[Poppins]">
    <div class="w-screen min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8" 
         style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url(./assets/draw.avif) center center / cover no-repeat;">
        <div class="relative w-full max-w-xs sm:max-w-sm py-3">
            <div class="px-6 sm:px-8 py-6 bg-gray-100 text-left rounded-xl shadow-lg">
                <div class="text-center mb-8">
                    <p class="text-[16px] font-bold">Login to your Account</p>
                </div>

                <!-- Display Error Message -->
                <?php if (isset($error) && !empty($error)): ?>
                    <p class="text-red-500 text-center mb-4"><?php echo $error; ?></p>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-4">
                    <div>
                        <label class="font-bold text-gray-500 text-xs">Email</label>
                        <input type="email" name="Email" id="email"
                               class="rounded-lg border px-3 py-2 w-full text-sm outline-indigo-50" 
                               placeholder="Email" required>
                    </div>
                    <div>
                        <label class="font-bold text-gray-500 text-xs">Password</label>
                        <input type="password" name="password" id="password"
                               class="rounded-lg border px-3 py-2 w-full text-sm outline-indigo-50" 
                               placeholder="Password" required>
                    </div>
                    <div class="flex justify-center">
                        <button type="submit" 
                                class="w-full sm:w-3/6 my-5 bg-blue-500 text-white p-2 rounded hover:bg-blue-700">
                            Login
                        </button>
                    </div>
                    <div class="text-center">
                        <a href="register.php" class="opacity-60">Create an account</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>