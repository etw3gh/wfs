<?php 
    require_once("FoursquareAPI.class.php");
    require_once("../../../secret.php");
    // Set your client key and secret
    $client_key = CLIENT_ID;
    $client_secret = CLIENT_SECRET;
    // Load the Foursquare API library

    $foursquare = new FoursquareAPI($client_key,$client_secret);
    $location = "Toronto, ON";

    $lat = 43.657233;
    $lng = -79.378499;
    
    //prepare params
    $params = array('ll' => "$lat, $lng") ;

    // Perform a request to a public resource
    $response = $foursquare->GetPublic("venues/search",$params);
    $venues = json_decode($response);

    $nested = array();

    print "number of venues returned was: " . count($venues->response->venues) . "<br /><hr><br />";

    foreach($venues->response->venues as $venue)
    {
        #print_r($venue);
        
        foreach($venue as $key => $value)
        {
            if (is_object($key) or is_object($value))
            {
                if (!in_array($nested, $key))
                {
                    array_push($nested, $key);
                }

                print $key . ": ";
                
                foreach($value as $nested_key => $nested_value)
                {
                    if (!is_object($nested_value))
                    {
                        print $nested_key . ": " . $nested_value . "<br />";
                    }
                }       
            }
            else {
                print $key . ": " . $value . "<br />";
            }
        }
        
        print "<br /><br /><hr><br /><br />";
   
    }
    
?>


