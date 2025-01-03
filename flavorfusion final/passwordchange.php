<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $role = 'Admin';
} else {
    $role = 'User'; // Default role or handle other roles as needed
}
// Redirect to login page if user is not logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Function to send data to RabbitMQ
function send_to_rabbitmq($data) {
    $connection = new AMQPStreamConnection('rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com', 5672, 'MQServer', 'IT490');
    $channel = $connection->channel();
    $channel->queue_declare('update_queue', false, false, false, false);

    $msg = new AMQPMessage($data);
    $channel->basic_publish($msg, '', 'update_queue');

    $channel->close();
    $connection->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $current_email = $_SESSION['email'];

    if ($type === 'email') {
        $new_email = $_POST['new_email'];
        $data = json_encode([
            'type' => 'email',
            'current_email' => $current_email,
            'new_email' => $new_email
        ]);

        send_to_rabbitmq($data);

        // Update session email if changed
        $_SESSION['email'] = $new_email;

    } elseif ($type === 'password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            die('Passwords do not match');
        }

        $data = json_encode([
            'type' => 'password',
            'current_email' => $current_email,
            'current_password' => $current_password,
            'new_password' => password_hash($new_password, PASSWORD_BCRYPT)
        ]);

        send_to_rabbitmq($data);

        // No need to update session email for password change

    } else {
        die('Invalid request type');
    }

    // Redirect after processing
    header("Location: passwordchange.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Change</title>
    <?php include 'nav.php'; ?>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/styles.css">
</head>
<body>

    <div class="container mt-4">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?>!</h1>

        <div class="card shadow">
            <div class="card-body">
                <h2 class="card-title">Change Email</h2>
                <form method="post" action="">
                    <input type="hidden" name="type" value="email">
                    <div class="form-group">
                        <label for="new_email">New Email:</label>
                        <input type="email" id="new_email" name="new_email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Email</button>
                </form>
            </div>
        </div>

        <div class="card mt-4 shadow">
            <div class="card-body">
                <h2 class="card-title">Change Password</h2>
                <form method="post" action="">
                    <input type="hidden" name="type" value="password">
                    <div class="form-group">
                        <label for="current_password">Current Password:</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
