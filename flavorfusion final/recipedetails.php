<?php
// Include session timeout management
session_start();
include 'sessiontimeout.php';
include 'nav.php'; 

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// RabbitMQ connection settings
$host = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
$port = 5672;
$user = 'MQServer';
$pass = 'IT490';
$vhost = '/';
$exchange = 'recipes_details';
$likes_queue = 'meal_like'; // New queue for sending likes
$response_queue = 'response_queue'; // Response queue for receiving replies
$delete_queue = 'delete_recipes_queue'; // Queue for deleting recipes
$countqueue = 'like_count'; // Queue for like count

try {
    // RabbitMQ connection
    $connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
    $channel = $connection->channel();

    // Check if recipe ID is provided in the URL
    if (!isset($_GET['id'])) {
        echo "<p>Error: Recipe ID not provided.</p>";
        exit;
    }

    $recipeId = $_GET['id'];

    // Fetch recipe details via RabbitMQ
    $correlationId = uniqid();

    // Declare response queue
    list($callback_queue, ,) = $channel->queue_declare("", false, false, true, false);

    $requestData = [
        'idMeal' => $recipeId,
        'email' => $_SESSION['email'] // Assuming $email is stored in the session
    ];

    // Convert request data to JSON
    $requestJson = json_encode($requestData);

    // Send request to RabbitMQ
    $msg = new AMQPMessage($requestJson, [
        'correlation_id' => $correlationId,
        'reply_to' => $callback_queue
    ]);
    $channel->basic_publish($msg, $exchange);

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

    $recipe = json_decode($response, true);

    // Close RabbitMQ connection
    $channel->close();
    $connection->close();

    // HTML content for recipe details
    ?>
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe Details</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    function toggleFavorite(button) {
        if (button.textContent === "Favorite") {
            button.textContent = "Unfavorite";
            sendFavorite(<?php echo $recipeId; ?>, true);
        } else {
            button.textContent = "Favorite";
            sendFavorite(<?php echo $recipeId; ?>, false);
        }
    }

    function sendFavorite(recipeId, isFavorite) {
        var formData = new FormData();
        formData.append('recipeId', recipeId);
        formData.append('isFavorite', isFavorite ? 'true' : 'false');
        formData.append('email', '<?php echo $_SESSION['email']; ?>'); // Add email to form data

        $.ajax({
            type: 'POST',
            url: 'send_favourite.php', // Endpoint to handle favorite/unfavorite action
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log(response);
                // Show pop-up notification
                var notification = document.createElement('div');
                notification.textContent = isFavorite ? 'Added to Favorites!' : 'Removed from Favorites!';
                notification.style.cssText = 'position: fixed; top: 10px; right: 10px; background-color: #4CAF50; color: white; padding: 15px; z-index: 1000;';
                document.body.appendChild(notification);

                setTimeout(function() {
                    notification.style.display = 'none';
                }, 3000); // Hide notification after 3 seconds
            },
            error: function(xhr, status, error) {
                console.error('Error sending favorite/unfavorite:', error);
            }
        });
    }
    
    function toggleLike(button) {
        if (button.textContent === "Like") {
            button.textContent = "Unlike";
            sendLike(<?php echo $recipeId; ?>, true);
        } else {
            button.textContent = "Like";
            sendLike(<?php echo $recipeId; ?>, false);
        }
    }

    function sendLike(recipeId, isLike) {
        var formData = new FormData();
        formData.append('recipeId', recipeId);
        formData.append('isLike', isLike ? 'true' : 'false');
        formData.append('email', '<?php echo $_SESSION['email']; ?>'); // Add email to form data

        $.ajax({
            type: 'POST',
            url: 'send_like.php', // Endpoint to handle like action
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log(response);
                // Show pop-up notification
                var notification = document.createElement('div');
                notification.textContent = isLike ? 'Liked!' : 'Unliked!';
                notification.style.cssText = 'position: fixed; top: 10px; right: 10px; background-color: #4CAF50; color: white; padding: 15px; z-index: 1000;';
                document.body.appendChild(notification);

                setTimeout(function() {
                    notification.style.display = 'none';
                }, 3000); // Hide notification after 3 seconds

                // Update like count dynamically after liking/unliking
                fetchLikeCount();
            },
            error: function(xhr, status, error) {
                console.error('Error sending like/unlike:', error);
            }
        });
    }

    function sendDelete(recipeId) {
        var formData = new FormData();
        formData.append('recipeId', recipeId);
        formData.append('email', '<?php echo $_SESSION['email']; ?>'); // Add email to form data

        $.ajax({
            type: 'POST',
            url: 'send_delete.php', // Endpoint to handle delete action
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                window.location.href = 'recipelist.php';
            },
            error: function(xhr, status, error) {
                console.error('Error deleting recipe:', error);
            }
        });
    }

    // Function to fetch and update like count
    function fetchLikeCount() {
        $.ajax({
            type: 'GET',
            url: 'fetchlike_count.php?recipeId=<?php echo $recipeId; ?>', // Endpoint to fetch like count
            success: function(response) {
                // Update like count on the page
                $('#total_likes').text('Like Count: ' + response);
            },
            error: function(xhr, status, error) {
                console.error('Error fetching like count:', error);
            }
        });
    }

    // Fetch initial like count on page load
    $(document).ready(function() {
        fetchLikeCount();
    });
    </script>
</head>
<body>
<div class="container mt-5">
    <?php
    // Output recipe details using Bootstrap cards
    if (isset($recipe['error'])) {
        echo "<div class='alert alert-danger'>{$recipe['error']}</div>";
    } else {
        echo "<div class='row'>";
        echo "  <div class='col-md-4'>";
        echo "    <img src='{$recipe['strMealThumb']}' class='img-fluid' alt='{$recipe['strMeal']}'>";
        echo "  </div>";
        echo "  <div class='col-md-8'>";
        echo "    <div class='card'>";
        echo "      <div class='card-header'><h1>{$recipe['strMeal']}</h1></div>";
        echo "      <div class='card-body'>";
        echo "        <h5 class='card-title'>Origin: {$recipe['strArea']}</h5>";

        echo "        <h6 class='card-subtitle mb-2 text-muted'>Ingredients</h6>";
        echo "        <ul class='list-group list-group-flush'>";
        for ($i = 1; $i <= 20; $i++) {
            $ingredient = $recipe["strIngredient{$i}"];
            $measure = $recipe["strMeasure{$i}"];
            if (!empty($ingredient)) {
                echo "<li class='list-group-item'>{$ingredient} - {$measure}</li>";
            }
        }
        echo "        </ul>";

        echo "        <h6 class='card-subtitle mb-2 text-muted mt-3'>Instructions</h6>";
        echo "        <p class='card-text'>{$recipe['strInstructions']}</p>";
        echo "        <p id='total_likes' class='card-text'>Like Count: </p>";

        if (!empty($recipe['strYoutube'])) {
            echo "        <h6 class='card-subtitle mb-2 text-muted'>Video</h6>";
            echo "        <a href='{$recipe['strYoutube']}' class='btn btn-primary'>Watch Video</a>";
        }

        echo "        <button id='likeButton' class='btn btn-primary mt-2' onclick='toggleLike(this)'>Like</button>";
        echo "        <button id='favoriteButton' class='btn btn-secondary mt-2' onclick='toggleFavorite(this)'>Favorite</button>";

        if (!isset($_SESSION['email']) || $_SESSION['role'] == 'admin') {
            echo "        <button class='btn btn-danger mt-2' onclick='sendDelete(" . $recipeId . ")'>Delete</button>";
        }
        
        echo "      </div>";
        echo "    </div>";
        echo "  </div>";
        echo "</div>";
    }
    ?>
</div>

</body>
<link rel="stylesheet" href="styles/styles.css">

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
