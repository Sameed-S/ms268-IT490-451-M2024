<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// RabbitMQ connection credentials
$host = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
$port = 5672;
$user = 'MQServer';
$pass = 'IT490';
$queue = 'meal_data_queue';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['action']) && $input['action'] === 'fetch_data') {
        $search = $input['search'] ?? '';
        $origin = $input['origin'] ?? '';
        $ingredientType = $input['ingredientType'] ?? '';
        $sort = $input['sort'] ?? 'name_asc';
        $page = $input['page'] ?? 1;
        $perPage = $input['perPage'] ?? 10;

        try {
            $connection = new AMQPStreamConnection($host, $port, $user, $pass);
            $channel = $connection->channel();

            $channel->queue_declare($queue, false, false, false, false);

            // Generate a unique correlation ID
            $correlationId = uniqid();

            $responseQueue = 'response_queue';
            $channel->queue_declare($responseQueue, false, false, false, false);

            $response = null;

            $callback = function ($msg) use (&$response, $correlationId) {
                // Debugging: Log the received message
                error_log("Received message: " . $msg->body);

                if ($msg->get('correlation_id') === $correlationId) {
                    $response = json_decode($msg->body, true);
                }
            };

            $channel->basic_consume($responseQueue, '', false, true, false, false, $callback);

            $msg = new AMQPMessage(
                json_encode([
                    'action' => 'fetch_data',
                    'search' => $search,
                    'origin' => $origin,
                    'ingredientType' => $ingredientType,
                    'sort' => $sort,
                    'page' => $page,
                    'perPage' => $perPage
                ]),
                ['correlation_id' => $correlationId, 'reply_to' => $responseQueue]
            );

            $channel->basic_publish($msg, '', $queue);

            // Wait for response up to 5 seconds
            $timeout = 5; // seconds
            $start = time();
            while (!$response && (time() - $start) < $timeout) {
                $channel->wait(null, false, $timeout);
            }

            // Respond with fetched data or error message
            if ($response) {
                header('Content-Type: application/json');
                echo json_encode($response);
            } else {
                http_response_code(504); // Gateway Timeout
                echo json_encode(['error' => 'No response from the server. Please try again later.']);
            }

            // Close channel and connection
            $channel->close();
            $connection->close();

        } catch (Exception $e) {
            error_log("Error in fetch_data.php: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'An error occurred. Please try again later.']);
        }
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid action.']);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed.']);
}
