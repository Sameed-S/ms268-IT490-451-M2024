<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php'; // RabbitMQ credentials

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// RabbitMQ configuration
$rabbitMQHost = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
$rabbitMQPort = '5672';
$rabbitMQUser = 'MQServer';
$rabbitMQPassword = 'IT490';
$RABBITMQ_QUEUE = 'test';

// Function to send data to RabbitMQ
function sendDataToRabbitMQ($data) {
    global $rabbitMQHost, $rabbitMQPort, $rabbitMQUser, $rabbitMQPassword, $RABBITMQ_QUEUE;

    // Establish RabbitMQ connection
    $connection = new AMQPStreamConnection($rabbitMQHost, $rabbitMQPort, $rabbitMQUser, $rabbitMQPassword);
    $channel = $connection->channel();

    // Declare the queue
    $channel->queue_declare($RABBITMQ_QUEUE, false, true, false, false);

    // Prepare message
    $messageBody = json_encode($data);
    $msg = new AMQPMessage($messageBody);

    // Publish message to RabbitMQ
    $channel->basic_publish($msg, '', $RABBITMQ_QUEUE);

    // Close channel and connection
    $channel->close();
    $connection->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs (for example, using filter_input or htmlspecialchars)
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Assuming basic validation here, you should implement proper validation and sanitization
    if (!empty($username) && !empty($password)) {
        // Assuming this is where you validate and store the user in your database
        // Insert into database (you should add proper validation, hashing, etc.)
        // Example: $db->query("INSERT INTO users (username, password) VALUES ('$username', '$password')");

        // Example data array to send to RabbitMQ
        $data = [
            'username' => $username,
            'password' => $password
        ];

        // Send data to RabbitMQ
        sendDataToRabbitMQ($data);

        echo 'Data sent to RabbitMQ successfully!';
    } else {
        echo 'Username and password are required fields!';
    }
}
?>
