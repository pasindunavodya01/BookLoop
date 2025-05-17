<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Database connection
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (!isset($_POST['request_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing request ID']);
        exit;
    }

    $request_id = intval($_POST['request_id']);
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $response = ['success' => false, 'message' => ''];

    try {
        // Get request details
        $stmt = $conn->prepare("SELECT r.*, b.title, u.email, u.first_name 
                               FROM requests r
                               JOIN books b ON r.book_id = b.book_id
                               JOIN users u ON r.requester_id = u.id
                               WHERE r.request_id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $requestDetails = $stmt->get_result()->fetch_assoc();

        if (!$requestDetails) {
            throw new Exception('Request not found');
        }

        // Process action
        if ($action === 'accept') {
            $conn->begin_transaction();

            // Generate OTPs
            $pickup_otp = generateOTP();
            $return_otp = generateOTP();

            // Update request status and OTPs
   $accepted_at = date('Y-m-d H:i:s'); // current timestamp

$updateReq = $conn->prepare("UPDATE requests SET status='Accepted', pickup_otp=?, return_otp=?, accepted_at=? WHERE request_id=?");
$updateReq->bind_param("sssi", $pickup_otp, $return_otp, $accepted_at, $request_id);

            $updateReq->execute();

            // Mark book as unavailable
            $updateBook = $conn->prepare("UPDATE books SET availability_status='Unavailable' WHERE book_id=?");
            $updateBook->bind_param("i", $requestDetails['book_id']);
            $updateBook->execute();

            $conn->commit();
            $response = ['success' => true, 'message' => 'Request accepted successfully!'];
        } elseif ($action === 'reject') {
            $updateReq = $conn->prepare("UPDATE requests SET status='Rejected' WHERE request_id=?");
            $updateReq->bind_param("i", $request_id);
            $updateReq->execute();

            $response = ['success' => true, 'message' => 'Request rejected successfully!'];
        } else {
            throw new Exception('Invalid action');
        }

        // Send email notification
        if ($response['success']) {
            sendRequestNotification(
                $requestDetails['email'],
                $requestDetails['first_name'],
                $requestDetails['title'],
                $action,
                $action === 'accept' ? $pickup_otp : null // Pass pickup_otp for accept action
            );
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Error in handle_request: " . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}

function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function sendRequestNotification($toEmail, $firstName, $bookTitle, $action, $pickup_otp = null) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'networksys20@gmail.com';
        $mail->Password = 'gvvf cnny fsnr yixv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = 0; // Set to 2 for debugging

        // Recipients
        $mail->setFrom('networksys20@gmail.com', 'BookLoop');
        $mail->addAddress($toEmail, $firstName);

        // Content
        $mail->isHTML(true);

        if ($action === 'accept') {
            $mail->Subject = 'Your Book Request Has Been Accepted';
            $mail->Body = "<h2>Hello $firstName,</h2>
                          <p>Your request for <strong>$bookTitle</strong> has been accepted!</p>
                          <p>Please contact the owner to arrange pickup.</p>
                          <p>Pickup OTP: <strong>$pickup_otp</strong></p>";
        } else {
            $mail->Subject = 'Your Book Request Has Been Declined';
            $mail->Body = "<h2>Hello $firstName,</h2>
                          <p>Your request for <strong>$bookTitle</strong> has been declined.</p>
                          <p>Feel free to browse other available books.</p>";
        }

        $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
    }
}

$conn->close();
?>