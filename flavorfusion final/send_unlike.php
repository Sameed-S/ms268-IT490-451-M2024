<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

session_start();

if (!isset($_SESSION['email'])) {
    echo "Error: User not logged in.";
    exit();
}

$email = $_SESSION['email'];

// RabbitMQ connection settings
$host = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
$port = 5672;
$user = 'MQServer';
$pass = 'IT490';
$vhost = '/';
$exchange = 'recipes_details';
$queue = 'recipe_likes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipeId = $_POST['recipeId'];

    $data = [
        'email' => $email,
        'idMeal' => $recipeId,
        'action' => 'unlike'
    ];

    try {
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

        // Send unlike request to RabbitMQ
        $msg = new AMQPMessage(json_encode($data), [
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

        $channel->close();
        $connection->close();

        echo "Unlike request sent successfully.";
    } catch (Exception $e) {
        error_log('RabbitMQ Error: ' . $e->getMessage());
        echo 'Error: Could not send unlike request.';
    }
} else {
    echo 'Error: Invalid request method.';
}
?>
