<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// RabbitMQ connection settings
$rabbitmq_host = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
$rabbitmq_port = 5672;
$rabbitmq_user = 'MQServer';
$rabbitmq_password = 'IT490';
$like_queue_name = 'like_count';

// Check if recipeId is provided
if (!isset($_GET['recipeId'])) {
    echo json_encode(['error' => 'Recipe ID not provided']);
    exit();
}

$recipeId = $_GET['recipeId'];

try {
    // Establish RabbitMQ connection and channel
    $connection = new AMQPStreamConnection($rabbitmq_host, $rabbitmq_port, $rabbitmq_user, $rabbitmq_password);
    $channel = $connection->channel();

    // Declare response queue
    list($callback_queue, ,) = $channel->queue_declare("", false, false, true, false);

    // Generate unique correlation ID
    $correlationId = uniqid();

    // Prepare request data
    $requestData = [
        'idMeal' => $recipeId,
        // Add any other necessary data
    ];

    // Convert request data to JSON
    $requestJson = json_encode($requestData);

    // Send request message to RabbitMQ
    $msg = new AMQPMessage($requestJson, [
        'correlation_id' => $correlationId,
        'reply_to' => $callback_queue
    ]);
    $channel->basic_publish($msg, '', $like_queue_name);

    // Callback function to handle response
    $response = null;
    $callback = function ($msg) use (&$response, $correlationId) {
        if ($msg->get('correlation_id') == $correlationId) {
            $response = $msg->body;
        }
    };

    // Consume response from RabbitMQ
    $channel->basic_consume($callback_queue, '', false, true, false, false, $callback);

    // Wait for the response
    while (!$response) {
        $channel->wait();
    }

    // Close RabbitMQ connection
    $channel->close();
    $connection->close();

    // Output response (like count) to frontend
    echo $response;

} catch (\PhpAmqpLib\Exception\AMQPConnectionException $e) {
    // Handle RabbitMQ connection errors
    echo json_encode(['error' => 'Failed to connect to RabbitMQ: ' . $e->getMessage()]);
} catch (\Exception $e) {
    // Handle other exceptions
    echo json_encode(['error' => 'Exception occurred: ' . $e->getMessage()]);
}
?>