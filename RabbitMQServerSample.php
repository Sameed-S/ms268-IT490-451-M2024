<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function login($user, $pass) {
    // TODO: validate user credentials
    return true;
}

function request_processor($req) {
    echo "Received Request" . PHP_EOL;
    echo "<pre>" . var_dump($req) . "</pre>";

    if (!isset($req['type'])) {
        echo "Error: unsupported message type" . PHP_EOL;
        return "Error: unsupported message type";
    }

    // Handle message type
    $type = $req['type'];
    $response = array("return_code" => '0', "message" => "Server received request and processed it");

    switch ($type) {
        case "login":
            $response = login($req['username'], $req['password']);
            break;
        case "validate_session":
            $response = validate($req['session_id']);
            break;
        case "echo":
            $response = array("return_code" => '0', "message" => "Echo: " . $req["message"]);
            break;
        default:
            echo "Error: unsupported message type" . PHP_EOL;
            return "Error: unsupported message type";
    }

    
    echo "Valid Response sent: " . PHP_EOL;
    print_r($response);
    echo PHP_EOL;

    return $response;
}

$server = new rabbitMQServer("testRabbitMQ.ini", "sampleServer");

echo "Rabbit MQ Server Start" . PHP_EOL;
$server->process_requests('request_processor');
echo "Rabbit MQ Server Stop" . PHP_EOL;
exit();
?>

