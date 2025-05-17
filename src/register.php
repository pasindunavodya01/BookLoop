<?php
include 'db.php'; // Include the database connection file


$email_error = ""; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['Fname'];
    $last_name = $_POST['Lname'];
    $email = $_POST['Email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM mails WHERE mails = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $insert_sql = "INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $role = 'user'; 
        $insert_stmt->bind_param("sssss", $first_name, $last_name, $email, $hashed_password, $role);

        // Execute the insertion and check if it was successful
        if ($insert_stmt->execute()) {
            // Set success message in session
            $_SESSION['success_message'] = "Registration successful! You can now log in.";

            header("Location: register.php");
            exit(); 
        } else {
            echo "<p class='text-red-500'>Error: " . $insert_stmt->error . "</p>";
        }

        $insert_stmt->close();
    } else {
        $email_error = "The email you entered is not valid."; 
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
         style="background: linear-gradient(rgba(0,0,0,0.6) , rgba(0,0,0,0.6)), url(./assets/draw.avif) center center / cover no-repeat;">
        <div class="relative w-full max-w-xs sm:max-w-sm py-3">
            <div class="px-6 sm:px-8 py-6 bg-gray-100 text-left rounded-xl shadow-lg">
                <div class="text-center mb-8">
                    <p class="text-[16px] font-bold">Create an Account</p>
                </div>

                <!-- Display Success Message -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="bg-green-100 text-green-700 text-center p-2 rounded mb-4">
                        <?php echo $_SESSION['success_message']; ?>
                    </div>
                    <?php unset($_SESSION['success_message']); // Clear the message after displaying ?>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-4">
                    <div>
                        <label class="font-bold text-gray-500 text-xs">First Name</label>
                        <input type="text" name="Fname" id="Fname" 
                               class="rounded-lg border px-3 py-2 w-full text-sm outline-indigo-50" 
                               placeholder="First Name" required>
                    </div>
                    <div>
                        <label class="font-bold text-gray-500 text-xs">Last Name</label>
                        <input type="text" name="Lname" id="Lname" 
                               class="rounded-lg border px-3 py-2 w-full text-sm outline-indigo-50" 
                               placeholder="Last Name" required>
                    </div>
                    <div>
                        <label class="font-bold text-gray-500 text-xs">Email</label>
                        <input type="text" name="Email" id="Email" 
                               class="rounded-lg border px-3 py-2 w-full text-sm outline-indigo-50" 
                               placeholder="Email" required>
                    </div>
                    <div>
                        <label class="font-bold text-gray-500 text-xs">Password</label>
                        <input type="password" name="password" id="password" 
                               class="rounded-lg border px-3 py-2 w-full text-sm outline-indigo-50" 
                               placeholder="Password" required>
                    </div>

                    <!-- Display error message if email is not valid -->
                    <?php if (!empty($email_error)): ?>
                        <p class="text-red-500 text-xs text-center"><?php echo $email_error; ?></p>
                    <?php endif; ?>

                    <div class="flex justify-center">
                        <button type="submit" 
                                class="w-full sm:w-3/6 my-5 bg-blue-500 text-white p-2 rounded hover:bg-blue-700">
                            Register
                        </button>
                    </div>
                    <div class="text-center">
                        <a href="login.php" class="opacity-60">Already have an account? <span class="text-blue-500">Sign in</span></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>