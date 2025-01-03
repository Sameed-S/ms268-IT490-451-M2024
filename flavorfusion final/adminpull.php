<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Include session timeout management
include 'sessiontimeout.php';
include 'nav.php';

// RabbitMQ connection credentials
$host = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
$port = 5672;
$user = 'MQServer';
$pass = 'IT490';
$requestQueue = 'api_queue';

function handleRabbitMQConnection($host, $port, $user, $pass) {
    try {
        $connection = new AMQPStreamConnection($host, $port, $user, $pass);
        return $connection->channel();
    } catch (Exception $e) {
        echo "RabbitMQ Connection Error: " . $e->getMessage();
        exit;
    }
}

// Establish RabbitMQ connection and channel
$channel = handleRabbitMQConnection($host, $port, $user, $pass);

// Declare the request queue
$channel->queue_declare($requestQueue, false, false, false, false);

// Handle POST requests for pulling data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $method = $data['method'];
    $dataValue = $data['data'];

    // Create a correlation ID
    $correlationId = uniqid();

    // Declare a temporary queue for responses
    list($callbackQueue,,) = $channel->queue_declare("", false, false, true, false);

    // Create message and send to RabbitMQ
    $message = json_encode(['method' => $method, 'data' => $dataValue]);
    $amqpMessage = new AMQPMessage($message, [
        'correlation_id' => $correlationId,
        'reply_to' => $callbackQueue,
        'content_type' => 'application/json',
        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
    ]);

    // Publish message to the request queue
    $channel->basic_publish($amqpMessage, '', $requestQueue);

    // Set up a consumer to listen for the response
    $response = null;
    $callback = function($msg) use (&$response, $correlationId) {
        if ($msg->get('correlation_id') === $correlationId) {
            $response = json_decode($msg->body, true);
        }
    };
    $channel->basic_consume($callbackQueue, '', false, true, false, false, $callback);

    // Wait for the response
    while (!$response) {
        try {
            $channel->wait();
        } catch (Exception $e) {
            echo "Error while waiting for response: " . $e->getMessage();
            break;
        }
    }

    // Return a simplified response to the client
    header('Content-Type: application/json');
    echo json_encode($response);

    // Close channel and connection
    $channel->close();
    $channel->getConnection()->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Pull Data</title>
    <link href="styles/styles.css" rel="stylesheet">
</head>
<body>
    <h2>Admin Pull Data</h2>

    <!-- Form to select the use case -->
    <form id="dataForm">
        <label for="method">Select Method:</label>
        <select id="method" name="method">
            <option value="getall">Get All</option>
            <option value="getone">Get One</option>
            <option value="getcat">Get Category</option>
        </select>

        <div id="dataInput" style="display: none;">
            <label for="data">Data:</label>
            <input type="text" id="data" name="data" placeholder="Enter data">
        </div>

        <button type="button" id="pullDataBtn">Pull New Data</button>
    </form>

    <div id="pullStatus"></div>

    <script>
        document.getElementById('method').addEventListener('change', function() {
            const dataInput = document.getElementById('dataInput');
            if (this.value === 'getone' || this.value === 'getcat') {
                dataInput.style.display = 'block';
            } else {
                dataInput.style.display = 'none';
            }
        });

        document.getElementById('pullDataBtn').addEventListener('click', function() {
            const method = document.getElementById('method').value;
            const data = document.getElementById('data').value;

            // Display message request sent
            let pullStatusDiv = document.getElementById('pullStatus');
            pullStatusDiv.innerHTML = `<p>Request sent: Method=${method}, Data=${data}</p>`;

            fetch('/adminpull.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ method: method, data: data })
            })
            .then(response => response.json())
            .then(data => {
                let pullStatusDiv = document.getElementById('pullStatus');
                if (data.status === 'error') {
                    pullStatusDiv.innerHTML = `<p style="color: red;">Error: ${data.message}</p>`;
                } else {
                    pullStatusDiv.innerHTML = `<p>Success: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error pulling data:', error);
                let pullStatusDiv = document.getElementById('pullStatus');
                pullStatusDiv.innerHTML = `<p style="color: red;">Error pulling data. Please try again later.</p>`;
            });
        });
    </script>
</body>
</html>
