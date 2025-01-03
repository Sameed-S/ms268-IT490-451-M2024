<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// RabbitMQ server connection details
$rabbitMQHost = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com'; 
$rabbitMQPort = '5672'; 
$rabbitMQUser = 'MQServer'; 
$rabbitMQPassword = 'IT490'; 

// Handle form submission and send message to RabbitMQ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and get form data
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';

    try {
        // Establish AMQP connection
        $connection = new AMQPStreamConnection($rabbitMQHost, $rabbitMQPort, $rabbitMQUser, $rabbitMQPassword);
        $channel = $connection->channel();

        // Prepare message payload as JSON
        $msgBody = json_encode([
            "type" => "register",
            "username" => $username,
            "password" => $password // Note: Consider hashing the password before sending in production
        ]);

        // Create a unique correlation ID
        $correlationId = uniqid();

        // Send message to RabbitMQ
        $msg = new AMQPMessage($msgBody, [
            'correlation_id' => $correlationId,
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT // Message should survive server restarts
        ]);

        // Declare the queue for registration requests
        $channel->queue_declare('register_request', false, true, false, false);

        // Publish message to RabbitMQ
        $channel->basic_publish($msg, '', 'register_request');

        // Close RabbitMQ connection
        $channel->close();
        $connection->close();

        echo "Registration request sent to RabbitMQ.";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
