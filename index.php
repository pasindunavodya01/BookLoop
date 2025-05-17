<?php
// Redirect immediately to the actual index.php in the src folder
header("Location: /src/index.php");
exit;  // Make sure to call exit after the redirect to stop further execution
?>
