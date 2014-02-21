<?php
    require_once("FoursquareAPI.class.php");
    require_once("../../../secret.php");
    // Set your client key and secret
    $client_key = CLIENT_ID;
    $client_secret = CLIENT_SECRET;
    // Load the Foursquare API library

    $foursquare = new FoursquareAPI($client_key,$client_secret);


    //OBTAIN A VENUE BY VENUE ID

    // Perform a request to a public resource
    $response = $foursquare->GetPublic("venues/4c0e64fe98102d7fca8be306");
    $venues = json_decode($response);

    print_r($response);



