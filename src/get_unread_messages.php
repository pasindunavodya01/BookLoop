<?php
function getUnreadMessagesCount($conn, $user_id) {
    $unreadQuery = "SELECT COUNT(*) as unread 
                    FROM messages 
                    WHERE receiver_id = $user_id 
                    AND read_status = 0
                    AND is_read = 0";
    $unreadResult = $conn->query($unreadQuery);
    return $unreadResult->fetch_assoc()['unread'];
}
?>