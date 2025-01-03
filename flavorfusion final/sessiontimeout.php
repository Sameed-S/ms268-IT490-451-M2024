<?php
// Check if the session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


define('SESSION_TIMEOUT', 300); 

// Check if the session is active and not expired
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
    // Session has expired, destroy and start a new one
    session_unset();     // unset $_SESSION variable for the run-time 
    session_destroy();   // destroy session data in storage
    session_start();     // start new session
}

// Update last activity time stamp
$_SESSION['LAST_ACTIVITY'] = time();

?>
