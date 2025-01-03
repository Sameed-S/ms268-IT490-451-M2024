<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Include session timeout management
include 'sessiontimeout.php';

$errors = [];

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Safely retrieve POST data with null coalescing operator
    $username = $_POST['username'] ?? null;
    $first_name = $_POST['first_name'] ?? null;
    $last_name = $_POST['last_name'] ?? null;
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;
    $confirm_password = $_POST['confirm_password'] ?? null;

    // Validate username length
    if (empty($username) || strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long!";
    }

    // Validate first and last name
    if (empty($first_name)) {
        $errors[] = "First name is required!";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required!";
    }

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required!";
    }

    // Validate password length
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long!";
    }

    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }

    if (empty($errors)) {
        // Send data to RabbitMQ
        $data = [
            'action' => 'register',
            'username' => $username,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'password' => $password
        ];
        $response = sendToRabbitMQ($data);

        // Check the response and redirect if successful
        if (isset($response['status']) && $response['status'] == 'success') {
            header("Location: login.php");
            exit();
        } elseif (isset($response['error']) && $response['error'] == 'Duplicate entry') {
            $errors[] = $response['message']; // Display the custom duplicate entry message
        } else {
            $errors[] = "Registration failed!";
        }
    }
}

function sendToRabbitMQ($data) {
    try {
        $connection = new AMQPStreamConnection('rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com', 5672, 'MQServer', 'IT490');
        $channel = $connection->channel();

        // Declare the queue for registration requests
        $channel->queue_declare('register_queue', false, true, false, false); 

        $correlationId = uniqid();

        // Create a new response queue
        list($responseQueue,,) = $channel->queue_declare("", false, false, true, false);

        // Preparing the message with correlation ID and reply-to queue
        $msg = new AMQPMessage(json_encode($data), [
            'correlation_id' => $correlationId,
            'reply_to' => $responseQueue,
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        // Publish the message to the queue
        $channel->basic_publish($msg, '', 'register_queue');

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'nav.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <!-- Registration Card -->
            <div class="col-md-6 mx-auto">
                <div class="card card-register">
                    <div class="card-body">
                        <h2 class="card-title">Register</h2>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors as $error): ?>
                                    <p><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" class="form-control" name="username" id="username" required value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="first_name">First Name:</label>
                                <input type="text" class="form-control" name="first_name" id="first_name" required value="<?php echo isset($first_name) ? htmlspecialchars($first_name) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name:</label>
                                <input type="text" class="form-control" name="last_name" id="last_name" required value="<?php echo isset($last_name) ? htmlspecialchars($last_name) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" class="form-control" name="email" id="email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="password">Password:</label>
                                <input type="password" class="form-control" name="password" id="password" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password:</label>
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Register</button>
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
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <link href="styles/styles.css" rel="stylesheet">

</body>
</html>
