<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
// Establish connection to RabbitMQ server
$connection = new AMQPStreamConnection('rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com', 5672, 'MQServer', 'IT490');
$channel = $connection->channel();

// Declare the queue
$channel->queue_declare('hello_queue', false, false, false, false);

// Message to send
$requestMessage = "Hello from front-end!";

// Publish message to the queue
$channel->basic_publish(new AMQPMessage($requestMessage), '', 'hello_queue');

echo " [x] Sent 'Hello' to RabbitMQ\n";

// Close the channel and connection
$channel->close();
$connection->close();
?>
