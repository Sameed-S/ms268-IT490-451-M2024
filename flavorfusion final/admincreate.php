<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include 'nav.php';

include 'sessiontimeout.php';

// RabbitMQ connection settings
try {
    $connection = new AMQPStreamConnection('rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com', 5672, 'MQServer', 'IT490');
    $channel = $connection->channel();
    $channel->queue_declare('recipes_queue', false, false, false, false);
    $channel->queue_declare('response_queue', false, false, false, false);
} catch (Exception $e) {
    error_log('RabbitMQ Connection Error: ' . $e->getMessage());
    echo 'Error: Could not connect to RabbitMQ.';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'idMeal' => $_POST['idMeal'],
        'strMeal' => $_POST['strMeal'],
        'strCategory' => $_POST['strCategory'],
        'strArea' => $_POST['strArea'],
        'strInstructions' => $_POST['strInstructions'],
        'strMealThumb' => $_POST['strMealThumb'],
        'strTags' => $_POST['strTags'],
        'strYoutube' => $_POST['strYoutube']
    ];

    for ($i = 1; $i <= 20; $i++) {
        $data['strIngredient' . $i] = $_POST['strIngredient' . $i];
        $data['strMeasure' . $i] = $_POST['strMeasure' . $i];
    }

    try {
        $msg = new AMQPMessage(json_encode($data));
        $channel->basic_publish($msg, '', 'recipes_queue');

        $response = $channel->basic_get('response_queue', true, null);
        if ($response) {
            $responseBody = json_decode($response->body, true);
            if (isset($responseBody['error'])) {
                echo $responseBody['error'];
            } else {
                echo 'Recipe submitted successfully';
            }
        }
    } catch (Exception $e) {
        error_log('RabbitMQ Publish Error: ' . $e->getMessage());
        echo 'Error: Could not submit the recipe.';
    }

    $channel->close();
    $connection->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Recipe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="styles/styles.css" rel="stylesheet"> 
</head>
<body>
    <div class="container">
        <h1>Create New Recipe</h1>
        <form id="recipeForm">
            <div class="form-group">
                <label for="idMeal">Meal number</label>
                <input type="text" class="form-control" id="idMeal" name="idMeal" required>
            </div>
            <div class="form-group">
                <label for="strMeal">Meal Name</label>
                <input type="text" class="form-control" id="strMeal" name="strMeal">
            </div>
            <div class="form-group">
                <label for="strCategory">Category</label>
                <input type="text" class="form-control" id="strCategory" name="strCategory">
            </div>
            <div class="form-group">
                <label for="strArea">Area</label>
                <input type="text" class="form-control" id="strArea" name="strArea">
            </div>
            <div class="form-group">
                <label for="strInstructions">Instructions</label>
                <textarea class="form-control" id="strInstructions" name="strInstructions" rows="5"></textarea>
            </div>
            <div class="form-group">
                <label for="strMealThumb">Meal Thumbnail URL</label>
                <input type="url" class="form-control" id="strMealThumb" name="strMealThumb">
            </div>
            <div class="form-group">
                <label for="strTags">Tags (comma separated)</label>
                <input type="text" class="form-control" id="strTags" name="strTags">
            </div>
            <div class="form-group">
                <label for="strYoutube">YouTube URL</label>
                <input type="url" class="form-control" id="strYoutube" name="strYoutube">
            </div>

            <?php for ($i = 1; $i <= 20; $i++): ?>
            <div class="form-group">
                <label for="strIngredient<?php echo $i; ?>">Ingredient <?php echo $i; ?></label>
                <input type="text" class="form-control" id="strIngredient<?php echo $i; ?>" name="strIngredient<?php echo $i; ?>">
            </div>
            <div class="form-group">
                <label for="strMeasure<?php echo $i; ?>">Measure <?php echo $i; ?></label>
                <input type="text" class="form-control" id="strMeasure<?php echo $i; ?>" name="strMeasure<?php echo $i; ?>">
            </div>
            <?php endfor; ?>

            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#recipeForm').on('submit', function(e) {
                e.preventDefault();

                var formData = $(this).serialize();

                $.ajax({
                    type: 'POST',
                    url: 'admincreate.php',
                    data: formData,
                    success: function(response) {
                        alert(response);
                        // Optionally clear the form or redirect to another page
                        $('#recipeForm')[0].reset();
                    },
                    error: function() {
                        alert('There was an error submitting the recipe.');
                    }
                });
            });
        });
    </script>
</body>
</html>