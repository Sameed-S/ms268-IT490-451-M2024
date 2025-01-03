<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents('php://input'), true);
    $method = $data['method'] ?? null;
    $requestData = $data['data'] ?? null;

    if ($method && $requestData) {
        $response = sendToRabbitMQ($method, $requestData);
        echo json_encode($response);
    } else {
        echo json_encode(['error' => 'Invalid input']);
    }
}

function sendToRabbitMQ($method, $data) {
    try {
        $connection = new AMQPStreamConnection('rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com', 5672, 'MQServer', 'IT490');
        $channel = $connection->channel();

        // Declare the request queue
        $channel->queue_declare('api_queue', false, false, false, false);

        // Create a new response queue
        list($responseQueue,,) = $channel->queue_declare("", false, false, true, false);

        // Generate a unique correlation ID
        $correlationId = uniqid();

        // Prepare the message with correlation ID and reply-to queue
        $msg = new AMQPMessage(json_encode(['method' => $method, 'data' => $data]), [
            'correlation_id' => $correlationId,
            'reply_to' => $responseQueue,
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        // Publish the message to the queue
        $channel->basic_publish($msg, '', 'api_queue');

        // Define a callback function to process the response
        $response = null;
        $callback = function($msg) use (&$response, $correlationId) {
            if ($msg->get('correlation_id') === $correlationId) {
                $response = json_decode($msg->body, true);
            }
        };

        // Consume messages from the response queue
        $channel->basic_consume($responseQueue, '', false, true, false, false, $callback);

        // Wait for a response
        $timeout = 10; // Timeout in seconds
        $start_time = time();
        while ($response === null && (time() - $start_time) < $timeout) {
            $channel->wait(null, false, $timeout);
        }

        // Close channel and connection
        $channel->close();
        $connection->close();

        // Handle the response
        if ($response) {
            if (isset($response['error'])) {
                return ['error' => $response['error']];
            } else {
                return ['success' => 'Data pulled successfully', 'data' => $response];
            }
        } else {
            return ['error' => 'No response received within the timeout period'];
        }

    } catch (Exception $e) {
        return ['error' => 'Error: ' . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Pull Data</title>
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

            fetch('', { // Use the current PHP file
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ method: method, data: data })
            })
            .then(response => response.json())
            .then(responseData => {
                let pullStatusDiv = document.getElementById('pullStatus');
                if (responseData.error) {
                    pullStatusDiv.innerHTML = `<p>Error pulling data: ${responseData.error}</p>`;
                } else {
                    pullStatusDiv.innerHTML = `<p>${responseData.success}</p>`;
                    if (responseData.data) {
                        pullStatusDiv.innerHTML += `<pre>${JSON.stringify(responseData.data, null, 2)}</pre>`;
                    }
                }
            })
            .catch(error => {
                console.error('Error pulling data:', error);
                let pullStatusDiv = document.getElementById('pullStatus');
                pullStatusDiv.innerHTML = `<p>Error pulling data. Please try again later.</p>`;
            });
        });
    </script>
</body>
</html>
