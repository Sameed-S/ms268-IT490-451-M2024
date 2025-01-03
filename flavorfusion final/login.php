<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$errors = [];

// Include session timeout management
include 'sessiontimeout.php';

if (isset($_SESSION['error'])) {
    $errors[] = $_SESSION['error'];
    unset($_SESSION['error']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Validate password
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }

    if (empty($errors)) {
        // Send data to RabbitMQ
        $data = ['action' => 'login', 'email' => $email, 'password' => $password];
        $response = sendToRabbitMQ($data);

        if ($response !== null && isset($response['status']) && $response['status'] == 'success') {
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $response['role']; // Store role in session
            header("Location: home.php");
            exit();
        } else {
            if ($response !== null && isset($response['error'])) {
                $errors[] = "Login failed: " . $response['error'];
            } else {
                $errors[] = "Login failed! Email or Password doesn't exist";
            }
        }
    }
}

function sendToRabbitMQ($data) {
    try {
        $connection = new AMQPStreamConnection('rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com', 5672, 'MQServer', 'IT490');
        $channel = $connection->channel();

        $channel->queue_declare('login_queue', false, false, false, false);

        // Create a new response queue
        list($responseQueue,,) = $channel->queue_declare("", false, false, true, false);

        // Generate a unique correlation ID
        $correlationId = uniqid();

        // Prepare the message with correlation ID and reply-to queue
        $msg = new AMQPMessage(json_encode($data), [
            'correlation_id' => $correlationId,
            'reply_to' => $responseQueue,
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        // Publish the message to the login queue
        $channel->basic_publish($msg, '', 'login_queue');

        // Consume the response from the created queue
        $response = null;
        $callback = function ($msg) use (&$response, $correlationId) {
            if ($msg->get('correlation_id') === $correlationId) {
                $response = json_decode($msg->body, true);
            }
        };

        $channel->basic_consume($responseQueue, '', false, true, false, false, $callback);

        // Wait for response
        $timeout = 10; // Timeout in seconds
        $start_time = time();
        while ($response === null && (time() - $start_time) < $timeout) {
            $channel->wait(null, false, $timeout);
        }

        // Close the channel and the connection
        $channel->close();
        $connection->close();

        return $response;

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        return null;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <?php include 'nav.php'; ?>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <!-- Carousel Card -->
            <div class="col-md-6">
                <div class="card card-carousel">
                    <div class="card-body">
                        <div id="carouselExampleIndicators" class="carousel slide" data-ride="carousel">
                            <ol class="carousel-indicators">
                                <li data-target="#carouselExampleIndicators" data-slide-to="0" class="active"></li>
                                <li data-target="#carouselExampleIndicators" data-slide-to="1"></li>
                                <li data-target="#carouselExampleIndicators" data-slide-to="2"></li>
                            </ol>
                            <div class="carousel-inner">
                                <div class="carousel-item active">
                                    <img src="styles/picture1.jpg" class="d-block w-100 carousel-img" alt="Picture 1">
                                </div>
                                <div class="carousel-item">
                                    <img src="styles/picture2.avif" class="d-block w-100 carousel-img" alt="Picture 2">
                                </div>
                                <div class="carousel-item">
                                    <img src="styles/picture3.webp" class="d-block w-100 carousel-img" alt="Picture 3">
                                </div>
                            </div>
                            <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="sr-only">Previous</span>
                            </a>
                            <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="sr-only">Next</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Login Card -->
            <div class="col-md-6">
                <div class="card card-login">
                    <div class="card-body">
                        <h2 class="card-title">Login</h2>

                        <?php
                        if (!empty($errors)) {
                            echo '<div class="alert alert-danger">';
                            foreach ($errors as $error) {
                                echo '<p>' . htmlspecialchars($error) . '</p>';
                            }
                            echo '</div>';
                        }
                        ?>

                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" class="form-control" name="email" id="email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="password">Password:</label>
                                <input type="password" class="form-control" name="password" id="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <span class="text-muted">&copy; Flavor Fusion, 2024 | Terms Of Use | Privacy Statement</span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <link href="styles/styles.css" rel="stylesheet"> 

</body>
</html>
