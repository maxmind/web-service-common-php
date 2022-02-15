<?php
// This program is used for testing the unit tests found in this package.
// It sends the response only once at startup that is sent to it through
// STDIN. Then, it needs to be restarted again to get the STDIN again.

// Getting the response that should be returned to the requester.
$responeJSON = readline("What response should be returned?");
$parsedJSON = json_decode($responeJSON);

// Set the content type
if ( property_exists($parsedJSON, "contentType")){
    header('Content-Type: '.$parsedJSON->contentType);
}

// Return the http status
if ( property_exists($parsedJSON, "status")){
    http_response_code(intval($parsedJSON->status));
}

// Return body
if ( property_exists($parsedJSON, "body")){
    echo $parsedJSON->body;
}

