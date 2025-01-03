<?php
require 'vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection('rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com', 5672, 'MQServer', 'IT490');
$channel = $connection->channel();

$channel->queue_declare('register_queue', false, true, false, false);

echo " [*] Waiting for messages. To exit press CTRL+C\n";

$callback = function ($msg) {
    $user_data = json_decode($msg->body, true);
    $email = $user_data['email'];
    $password = $user_data['password'];

    $mysqli = new mysqli("localhost", "your_username", "your_password", "your_database");

    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }

    $stmt = $mysqli->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "Email already exists.";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $email, $password);

        if ($stmt->execute()) {
            echo "User registered successfully.";
        } else {
            echo "Error: " . $stmt->error;
        }
    }

    $stmt->close();
    $mysqli->close();
};

$channel->basic_consume('register_queue', '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
