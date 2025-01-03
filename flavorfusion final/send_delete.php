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
$exchange = ''; // Ensure to set the correct exchange if needed
$queue = 'delete_recipes_queue'; // Queue for deleting recipes

// Check if the form is submitted for action processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipeId'])) {
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

        // Prepare delete data
        $deleteData = [
            'email' => $email,
            'idMeal' => $_POST['recipeId'],
            'action' => 'delete' // Specify action as delete
        ];

        // Convert delete data to JSON
        $deleteJson = json_encode($deleteData);

        // Send delete to RabbitMQ
        $msg = new AMQPMessage($deleteJson, [
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

        echo "Delete request sent successfully for recipe ID: {$_POST['recipeId']}";

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
