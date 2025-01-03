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

    // RabbitMQ connection settings
    $host = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
    $port = 5672;
    $user = 'MQServer';
    $pass = 'IT490';
    $vhost = '/';
    $userFetchQueue = 'user_fetch';
    $userDeleteQueue = 'user_delete';

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
    $channel->queue_declare($userFetchQueue, false, true, false, false);
    $channel->queue_declare($userDeleteQueue, false, true, false, false);

    // Fetch users
    $correlationIdUserFetch = uniqid();
    $responseUserFetch = null;

    $callbackUserFetch = function ($msg) use (&$responseUserFetch, $correlationIdUserFetch) {
        if ($msg->get('correlation_id') == $correlationIdUserFetch) {
            $responseUserFetch = $msg->body;
            error_log("User fetch response received: " . $responseUserFetch);
        }
    };

    // Set up basic consume for user fetch
    $callbackQueueUserFetch = $channel->queue_declare('', false, false, true, false)[0];
    $channel->basic_consume($callbackQueueUserFetch, '', false, true, false, false, $callbackUserFetch);

    // Send request to RabbitMQ for user fetch
    $requestMsgUserFetch = new AMQPMessage('', [
        'correlation_id' => $correlationIdUserFetch,
        'reply_to' => $callbackQueueUserFetch
    ]);
    $channel->basic_publish($requestMsgUserFetch, '', $userFetchQueue);
    error_log("User fetch request sent.");

    // Wait for user fetch response with timeout
    $startTime = time();
    $timeout = 30; // 30 seconds timeout

    while (!$responseUserFetch) {
        $channel->wait();
        if ((time() - $startTime) > $timeout) {
            throw new Exception('Timed out waiting for user fetch response');
        }
    }

    // Decode response for user fetch
    $users = json_decode($responseUserFetch, true);

    // Handle user deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
        $userIdToDelete = (int)$_POST['delete_user'];

        // Delete user through RabbitMQ
        $correlationIdUserDelete = uniqid();
        $requestDataUserDelete = ['userId' => $userIdToDelete];
        $requestJsonUserDelete = json_encode($requestDataUserDelete);

        $responseUserDelete = null;

        $callbackUserDelete = function ($msg) use (&$responseUserDelete, $correlationIdUserDelete) {
            if ($msg->get('correlation_id') == $correlationIdUserDelete) {
                $responseUserDelete = $msg->body;
                error_log("User delete response received: " . $responseUserDelete);
            }
        };

        $callbackQueueUserDelete = $channel->queue_declare('', false, false, true, false)[0];
        $channel->basic_consume($callbackQueueUserDelete, '', false, true, false, false, $callbackUserDelete);

        $requestMsgUserDelete = new AMQPMessage($requestJsonUserDelete, [
            'correlation_id' => $correlationIdUserDelete,
            'reply_to' => $callbackQueueUserDelete
        ]);
        $channel->basic_publish($requestMsgUserDelete, '', $userDeleteQueue);
        error_log("User delete request sent for user ID: $userIdToDelete.");

        // Wait for user delete response with timeout
        $startTime = time();
        while (!$responseUserDelete) {
            $channel->wait();
            if ((time() - $startTime) > $timeout) {
                throw new Exception('Timed out waiting for user delete response');
            }
        }

        // Check delete response
        $deleteResult = json_decode($responseUserDelete, true);
        if ($deleteResult['success']) {
            echo "<p>User with ID $userIdToDelete deleted successfully.</p>";
        } else {
            echo "<p>Failed to delete user with ID $userIdToDelete.</p>";
        }

        // Refresh page to update user list
        header("Refresh:0");
        exit();
    }

    // Include navigation bar
    include 'nav.php';

    // Display users in a table
    echo '<div class="container mt-4">';
    echo '<h1 class="mb-4">User Management</h1>';
    echo '<table class="table table-striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Email</th>';
    echo '<th>Action</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($users as $user) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($user['id']) . '</td>';
        echo '<td>' . htmlspecialchars($user['email']) . '</td>';
        echo '<td>';
        echo '<form method="post" action="" class="d-inline">';
        echo '<button type="submit" name="delete_user" value="' . htmlspecialchars($user['id']) . '" class="btn btn-danger">Delete</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '<link rel="stylesheet" href="styles/styles.css">';

} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
