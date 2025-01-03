 <?php
    require_once __DIR__ . '/vendor/autoload.php'; // Composer autoload
    use PhpAmqpLib\Connection\AMQPStreamConnection;
    use PhpAmqpLib\Message\AMQPMessage;

    // RabbitMQ server connection details
    $rabbitMQHost = 'rabbitmq-elb-dad86311aae5d992.elb.us-east-1.amazonaws.com';
    $rabbitMQPort = '5672';
    $rabbitMQUser = 'MQServer';
    $rabbitMQPassword = 'IT490';

    // Function to sanitize input data
    function sanitize($data) {
        return htmlspecialchars(addslashes(trim($data)));
    }

    // Check if form is submitted via POST
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Sanitize input data
        $username = sanitize($_GET['username']);
        $password = sanitize($_GET['password']);
        $passwordHash = md5($password); // Example hashing for password (not secure, use password_hash in production)

        // Establish AMQP connection
        $connection = new AMQPStreamConnection($rabbitMQHost, $rabbitMQPort, $rabbitMQUser, $rabbitMQPassword);
        $channel = $connection->channel();

        // Prepare message for RabbitMQ
        $msgBody = [
            "username" => $username,
            "passwordHash" => $passwordHash
        ];

        // Convert message to JSON (if needed)
        // $msgJson = json_encode($msgBody);

        // Declare queue and exchange
        $channel->queue_declare('validation_request', false, true, false, false, false, []);

        // Create AMQP message
        $msg = new AMQPMessage(serialize($msgBody), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        // Publish message to RabbitMQ
        $channel->basic_publish($msg, '', 'validation_request');

        // Close RabbitMQ channel and connection
        $channel->close();
        $connection->close();
        echo '<p>Registration request sent. Please wait for confirmation.</p>';
    }
    ?>

