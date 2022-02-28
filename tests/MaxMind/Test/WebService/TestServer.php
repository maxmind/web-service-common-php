<?php

// This program is used for testing the unit tests found in this package.
// It is a test server that is ran by ClientTest.php as a seperate process.
// It uses the built-in PHP server.

if ($_SERVER['REQUEST_URI'] === '/test') { // For checking if the server is up
    exit(0);
}

if ($_SERVER['REQUEST_URI'] === '/mirror') { // For checking if the server is up
    $INPUT = file_get_contents('php://input');
    $vars = get_defined_vars();
    echo json_encode($vars);

    return;
}

// Getting the location of the tmp response json file.
$fullResponseFilePath = getenv('RESPONSEJSON') ?: getenv('RESPONSEJSON');

// If there is no response file, return empty
if (!file_exists($fullResponseFilePath)) {
    exit(0);
}

// Consume a response from the response file
$contents = file($fullResponseFilePath, \FILE_IGNORE_NEW_LINES);
$responseJSON = array_shift($contents);
$parsedJSON = json_decode($responseJSON);
file_put_contents($fullResponseFilePath, implode(\PHP_EOL, $contents));

// Set the content type
if (property_exists($parsedJSON, 'contentType')) {
    header('Content-Type: ' . $parsedJSON->contentType);
}

// Return the http status
if (property_exists($parsedJSON, 'status')) {
    http_response_code((int) ($parsedJSON->status));
}

// Return body
if (property_exists($parsedJSON, 'body')) {
    echo $parsedJSON->body;
}
