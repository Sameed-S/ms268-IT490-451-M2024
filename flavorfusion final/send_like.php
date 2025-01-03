<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$host = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
$port = 5672;
$user = 'MQServer';
$pass = 'IT490';
$vhost = '/';
$likes_queue = 'meal_like'; // Queue for receiving likes

// Check if the form is submitted for action processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipeId']) && isset($_POST['isLike'])) {
    try {
        // RabbitMQ connection
        $connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
        $channel = $connection->channel();

        // Declare exchange and queue
        $channel->queue_declare($likes_queue, false, true, false, false);

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

        // // Determine if the action is a like or unlike
        // if (isset($_POST['isLike'])) {
        //     $isLike = filter_var($_POST['isLike'], FILTER_VALIDATE_BOOLEAN); // Validate as boolean
        // } else {
        //     echo "Error: Like status not provided.";
        //     exit;
        // }

        // Prepare data to send to RabbitMQ
        $likeData = [
            'email' => $email,
            'idMeal' => $_POST['recipeId'],
            'isLike' => $_POST['isLike'] === 'true', // Convert to boolean
            'action' => 'like' // Specify action as favorite
        ];

        // Convert data to JSON
        $likeJson = json_encode($likeData);

        // Debug: Print out the JSON being sent to RabbitMQ
        echo "Sending JSON to RabbitMQ: " . $likeJson . "\n";

        // Send message to RabbitMQ
        $msg = new AMQPMessage($likeJson, [
            'correlation_id' => $correlationId,
            'reply_to' => $callback_queue
        ]);
        $channel->basic_publish($msg, $exchange, $likes_queue);

        // Wait for response from RabbitMQ
        $channel->basic_consume($callback_queue, '', false, true, false, false, $callback);

        // Loop until response is received
        while (!isset($response)) {
            $channel->wait();
        }

        // Close RabbitMQ connection
        $channel->close();
        $connection->close();

        // Return response to frontend
        echo $response;

    } catch (\PhpAmqpLib\Exception\AMQPConnectionException $e) {
        // Handle RabbitMQ connection errors
        echo "Failed to connect to RabbitMQ: " . $e->getMessage();
    } catch (\Exception $e) {
        // Handle other exceptions
        echo "Exception occurred: " . $e->getMessage();
    }
} else {
    // Handle cases where form data is not correctly submitted
    echo "Error: Invalid request.";
}
?>
