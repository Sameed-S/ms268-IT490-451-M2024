<?php
// Start or resume session
include 'sessiontimeout.php';

// Include Bootstrap and custom styles
echo '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">';

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Redirect to login page if user is not logged in
if (!isset($_SESSION['email'])) {
    $_SESSION['error'] = "You must be logged in to view this page.";
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['email'];

// Handle category filter
$category = isset($_POST['category']) ? $_POST['category'] : null;

// Connect to RabbitMQ
try {
    // RabbitMQ connection settings
    $connection = new AMQPStreamConnection('rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com', 5672, 'MQServer', 'IT490');
    $channel = $connection->channel();

    // Declare queue
    $channel->queue_declare('random_meal_queue', false, true, false, false);

    // Unique correlation ID for the request
    $correlationId = uniqid();

    // Response variable
    $response = null;

    // Callback function to handle the response
    $callback = function ($msg) use (&$response, $correlationId) {
        if ($msg->get('correlation_id') == $correlationId) {
            $response = json_decode($msg->body, true);
            error_log("Meal data received: " . print_r($response, true));
        }
    };

    // Set up basic consume
    $callbackQueue = $channel->queue_declare('', false, false, true, false)[0];
    $channel->basic_consume($callbackQueue, '', false, true, false, false, $callback);

    // Prepare request data
    $requestData = ['category' => $category];
    $requestJson = json_encode($requestData);

    // Send request to RabbitMQ
    $requestMsg = new AMQPMessage($requestJson, [
        'correlation_id' => $correlationId,
        'reply_to' => $callbackQueue
    ]);
    $channel->basic_publish($requestMsg, '', 'random_meal_queue');
    error_log("Meal request sent.");

    // Wait for response
    $startTime = time();
    $timeout = 30; // 30 seconds timeout

    while (!$response) {
        $channel->wait();
        if ((time() - $startTime) > $timeout) {
            throw new Exception('Timed out waiting for meal response');
        }
    }

    // Close connection
    $channel->close();
    $connection->close();

    // Include navigation bar
    include 'nav.php';

    // Display meal information
    if ($response) {
        echo '<div class="container mt-4">';
        echo '<h1 class="mb-4">Meal of the Day</h1>';
        echo '<div class="card mb-3">';
        echo '<img src="' . htmlspecialchars($response['strMealThumb']) . '" class="card-img-top" alt="' . htmlspecialchars($response['strMeal']) . '">';
        echo '<div class="card-body">';
        echo '<h5 class="card-title">' . htmlspecialchars($response['strMeal']) . '</h5>';
        echo '<p class="card-text">Category: ' . htmlspecialchars($response['strCategory']) . '</p>';
        echo '<p class="card-text">Area: ' . htmlspecialchars($response['strArea']) . '</p>';
        
        // Use idMeal for More Information link
        echo "<a href='recipedetails.php?id={$response['idMeal']}' class='btn btn-primary'>More Information</a>";

        echo '</div>';
        echo '</div>';
        echo '<form method="post" action="">';
        echo '<div class="form-group">';
        echo '<label for="category">Filter by Category:</label>';
        echo '<select class="form-control" id="category" name="category">';
        echo '<option value="">Any</option>';
        echo '<option value="Beef" ' . ($category === 'Beef' ? 'selected' : '') . '>Beef</option>';
        echo '<option value="Chicken" ' . ($category === 'Chicken' ? 'selected' : '') . '>Chicken</option>';
        echo '<option value="Seafood" ' . ($category === 'Seafood' ? 'selected' : '') . '>Seafood</option>';
        echo '<option value="Vegetarian" ' . ($category === 'Vegetarian' ? 'selected' : '') . '>Vegetarian</option>';
        echo '</select>';
        echo '</div>';
        echo '<button type="submit" class="btn btn-success">Reroll</button>';
        echo '</form>';
        echo '</div>';
    }
    echo '<link rel="stylesheet" href="styles/styles.css">';

} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
