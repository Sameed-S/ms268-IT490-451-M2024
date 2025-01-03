<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// RabbitMQ connection settings
$host = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
$port = 5672;
$user = 'MQServer';
$pass = 'IT490';
$vhost = '/';
$queue = 'recipe_fav'; // Queue for sending likes

// Check if the form is submitted for action processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipeId']) && isset($_POST['isFavorite'])) {
    try {
        // Create connection
        $connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
        $channel = $connection->channel();

        $correlationId = uniqid();

        // Declare response queue
        list($callback_queue, ,) = $channel->queue_declare("", false, false, true, false);

        $callback = function ($msg) use ($correlationId, &$response) {
            if ($msg->get('correlation_id') == $correlationId) {
                $response = $msg->body;
            }
        };

        // Retrieve user email from session
        session_start(); // Ensure session is started
        if (isset($_SESSION['email'])) {
            $email = $_SESSION['email'];
        } else {
            // Handle case where email is not set, perhaps redirect to login
            echo "Error: User email not found in session.";
            exit;
        }

        // Prepare favorite data
        $favoriteData = [
            'email' => $email,
            'idMeal' => $_POST['recipeId'],
            'isFavorite' => $_POST['isFavorite'] === 'true', // Convert to boolean
            'action' => 'favorite' // Specify action as favorite
        ];

        // Convert favorite data to JSON
        $favoriteJson = json_encode($favoriteData);

        // Send favorite to RabbitMQ
        $msg = new AMQPMessage($favoriteJson, [
            'correlation_id' => $correlationId,
            'reply_to' => $callback_queue
        ]);
        $channel->basic_publish($msg, $exchange, $queue);

        // Wait for the response
        $channel->basic_consume($callback_queue, '', false, true, false, false, $callback);

        // Loop until response is received
        while (!isset($response)) {
            $channel->wait();
        }

        echo "Favorite request sent successfully for recipe ID: {$_POST['recipeId']}";

        // Close connection
        $channel->close();
        $connection->close();
    } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
        echo "Error: Timeout connecting to RabbitMQ";
        error_log("RabbitMQ Timeout Exception: " . $e->getMessage());
    } catch (\Exception $e) {
        echo "Error: Failed to process action for recipe ID: {$_POST['recipeId']}";
        error_log("Exception occurred: " . $e->getMessage());
    }
} else {
    echo "Error: Invalid request";
}
?>
