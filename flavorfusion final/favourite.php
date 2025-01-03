<?php
// Start or resume session
include 'sessiontimeout.php';

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    // Redirect to login page if user is not logged in
    if (!isset($_SESSION['email'])) {
        $_SESSION['error'] = "You must be logged in to view this page.";
        header("Location: login.php");
        exit();
    }

    // Fetch user email from session
    $userEmail = $_SESSION['email']; // Assuming 'email' is the session key for the user

    // Pagination parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // RabbitMQ connection settings
    $host = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
    $port = 5672;
    $user = 'MQServer';
    $pass = 'IT490';
    $vhost = '/';
    $exchange = 'user_profiles';
    $favorites_queue = 'favorites_queue';
    $responseQueue = 'response_queue';

    // Create RabbitMQ connection
    $connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
    $channel = $connection->channel();

    // Declare the exchange and queue
    $channel->exchange_declare($exchange, 'direct', false, true, false);
    list($callback_queue, ,) = $channel->queue_declare('', false, false, true, false);

    // Generate a unique correlation ID
    $correlationId = uniqid();

    // Prepare request data with pagination
    $requestData = [
        'userEmail' => $userEmail,
        'limit' => $limit,
        'offset' => $offset
    ];

    // Convert request data to JSON
    $requestJson = json_encode($requestData);

    // Response variable
    $response = null;

    // Callback function to handle response
    $callback = function ($msg) use (&$response, $correlationId) {
        if ($msg->get('correlation_id') == $correlationId) {
            $response = $msg->body;
        }
    };

    // Set up basic consume
    $channel->basic_consume($callback_queue, '', false, true, false, false, $callback);

    // Send request to RabbitMQ
    $requestMsg = new AMQPMessage($requestJson, [
        'correlation_id' => $correlationId,
        'reply_to' => $callback_queue
    ]);
    $channel->basic_publish($requestMsg, $exchange);

    // Wait for response
    while (!$response) {
        $channel->wait();
    }

    // Close RabbitMQ connection
    $channel->close();
    $connection->close();

    // Decode response
    $favorites = json_decode($response, true);

    // Output HTML content
    include 'nav.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Your Favorite Recipes</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
        <link href="styles/styles.css" rel="stylesheet"> 

    </head>
    <body>
        <div class="container">
            <h1>Your Favorite Recipes</h1>
            <div class="row">
                <?php
                if (isset($favorites['error'])) {
                    echo "<div class='col'><p class='alert alert-danger'>Error: {$favorites['error']}</p></div>";
                } elseif (empty($favorites)) {
                    echo "<div class='col'><p class='alert alert-info'>You have no favorite recipes.</p></div>";
                } else {
                    foreach ($favorites as $favorite) {
                        echo '<div class="col-md-4 mb-4">';
                        echo '<div class="card">';
                        echo "<img src='{$favorite['picture']}' class='card-img-top' alt='{$favorite['strMeal']}'>";
                        echo '<div class="card-body">';
                        echo "<h5 class='card-title'>{$favorite['strMeal']}</h5>";
                        echo "<a href='recipedetails.php?id={$favorite['idMeal']}' class='btn btn-primary'>More Information</a>";
                        echo '</div>'; // Close card-body
                        echo '</div>'; // Close card
                        echo '</div>'; // Close col
                    }
                }
                ?>
            </div> <!-- Close row -->
            
            <!-- Pagination controls -->
            <nav>
                <ul class="pagination">
                    <?php
                    $prevOffset = max(0, $offset - $limit);
                    $nextOffset = $offset + $limit;
                    echo '<li class="page-item' . ($offset == 0 ? ' disabled' : '') . '">';
                    echo "<a class='page-link' href='profile.php?limit={$limit}&offset={$prevOffset}'>Previous</a>";
                    echo '</li>';
                    echo '<li class="page-item">';
                    echo "<a class='page-link' href='profile.php?limit={$limit}&offset={$nextOffset}'>Next</a>";
                    echo '</li>';
                    ?>
                </ul>
            </nav>
        </div> <!-- Close container -->

        <!-- Bootstrap JS and dependencies (if needed) -->
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@1.16.1/dist/umd/popper.min.js" integrity="sha384-VG9S2C0QVWLSmAWbKstkxjhN5Yfo3+0R/3j03Ku3it9z1z4G6nD2hFExddu5J1n1" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous"></script>
    </body>

    </html>
    <?php
} catch (\PhpAmqpLib\Exception\AMQPConnectionException $e) {
    // Handle RabbitMQ connection errors
    echo "Failed to connect to RabbitMQ: " . $e->getMessage();
} catch (\Exception $e) {
    // Handle other exceptions
    echo "Exception occurred: " . $e->getMessage();
}
?>
