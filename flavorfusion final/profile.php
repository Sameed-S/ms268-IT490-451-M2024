<?php
// Start or resume session
include 'sessiontimeout.php';

// Include Bootstrap and custom styles
echo '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">';

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    // Redirect to login page if user is not logged in
    if (!isset($_SESSION['email'])) {
        $_SESSION['error'] = "You must be logged in to view this page.";
        header("Location: login.php");
        exit();
    }

    // Fetch user email from session
    $userEmail = $_SESSION['email'];

    // RabbitMQ connection settings
    $host = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
    $port = 5672;
    $user = 'MQServer';
    $pass = 'IT490';
    $vhost = '/';
    $userInfoQueue = 'user_info';

    // Create RabbitMQ connection
    try {
        $connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
        $channel = $connection->channel();
        error_log("RabbitMQ connection established.");
    } catch (Exception $e) {
        error_log("Failed to connect to RabbitMQ: " . $e->getMessage());
        exit();
    }

    // Declare the queues
    $channel->queue_declare($userInfoQueue, false, true, false, false);

    // Generate a unique correlation ID for user info request
    $correlationIdUserInfo = uniqid();

    // Prepare request data for user profile information
    $requestDataUserInfo = [
        'userEmail' => $userEmail,
        'fields' => ['username', 'first_name', 'last_name', 'email']
    ];
    $requestJsonUserInfo = json_encode($requestDataUserInfo);

    // Response variable for user info
    $responseUserInfo = null;

    // Callback function to handle user info response
    $callbackUserInfo = function ($msg) use (&$responseUserInfo, $correlationIdUserInfo) {
        if ($msg->get('correlation_id') == $correlationIdUserInfo) {
            $responseUserInfo = $msg->body;
            error_log("User info response received: " . $responseUserInfo);
        }
    };

    // Set up basic consume for user info
    $callbackQueueUserInfo = $channel->queue_declare('', false, false, true, false)[0];
    $channel->basic_consume($callbackQueueUserInfo, '', false, true, false, false, $callbackUserInfo);

    // Send request to RabbitMQ for user profile information
    $requestMsgUserInfo = new AMQPMessage($requestJsonUserInfo, [
        'correlation_id' => $correlationIdUserInfo,
        'reply_to' => $callbackQueueUserInfo
    ]);
    $channel->basic_publish($requestMsgUserInfo, '', $userInfoQueue);
    error_log("User info request sent.");

    // Wait for user profile response with timeout
    $startTime = time();
    $timeout = 30; // 30 seconds timeout

    while (!$responseUserInfo) {
        $channel->wait();
        if ((time() - $startTime) > $timeout) {
            throw new Exception('Timed out waiting for user info response');
        }
    }

    // Decode response for user profile
    $userInfo = json_decode($responseUserInfo, true);
    
    // Include navigation bar
    include 'nav.php';

    // Display user profile
    echo '<div class="container mt-4">';
    echo '<h1 class="mb-4">User Profile</h1>';
    echo '<div class="card">';
    echo '<div class="card-body">';
    echo '<p><strong>Username:</strong> ' . htmlspecialchars($userInfo['username']) . '</p>';
    echo '<p><strong>First Name:</strong> ' . htmlspecialchars($userInfo['first_name']) . '</p>';
    echo '<p><strong>Last Name:</strong> ' . htmlspecialchars($userInfo['last_name']) . '</p>';
    echo '<p><strong>Email:</strong> ' . htmlspecialchars($userInfo['email']) . '</p>';
    echo '</div>';
    echo '</div>';
    echo '<link href="styles/styles.css" rel="stylesheet">';

    // Add a button to redirect to favorites.php
    echo '<div class="mt-4">';
    echo '<a href="favourite.php" class="btn btn-primary">View Favorites</a>';
    echo '</div>';
    
    echo '</div>';

} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
