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

    $food_4s_id = '4d4b7105d754a06374d81259';
    $arts_4s_id = '4d4b7104d754a06370d81259';
    $bar_4s_id  = '4d4b7105d754a06376d81259';
    $shopping_4s_id = '4d4b7105d754a06378d81259';
    $travel_4s_id = '4d4b7105d754a06379d81259';

    $categories = $food_4s_id . ',' . $arts_4s_id . ',' . $bar_4s_id . ',' . $shopping_4s_id . ',' . $travel_4s_id;

        $params = array('ll' => "$lat, $lng",
                        'categoryId' => $categories,
                        #' ' =>
                        'radius' => 2000) ;

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


