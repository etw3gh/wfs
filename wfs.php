<?php
    require_once("FoursquareAPI.class.php");
    require_once('RestServer.php');


    // http://wfs.openciti.ca?method=MethodName&param1=Param1Value&param2=Param2Value
    class WarFareSquare
    {
        //example of a function
        public function hello($name){return(array("response" => "Hello $name"));}

        
        #each endpoint has its own function
        
        //please md5 encode the password
        public function register_user($username, $password, $first, $last, $lat=null, $lng=null, $full_response='false') 
        {
            //must be repeated for each api endpoint function due to RestServer functionality
            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $users = $wfs->selectCollection('users');
            $users->ensureIndex(array("username" => 1), array("unique" => 1));

            $insert_array = null;

            try{
                $insert_array = array('username' => $username, 
                                      'password' => $password, 
                                      'first' => $first , 
                                      'last' => $last, 
                                      'lat' => $lat,
                                      'lng' => $lng);

                $users->insert($insert_array);
                $return_code = 'ok';
            }
            catch(MongoCursorException $e) 
            {
                return array("response" => "duplicate user");
            }
            catch(MongoException $e)
            {
                return array("response" => "fail");
            }

            if(strtolower($full_response) == 'true' )
            {
                $insert_array["response"] = $return_code;            
                return $insert_array;            
            }
            else
            {
                return array("response" => $return_code);
            }
        }
 
        //remember to md5 encode password
        public function login_user($username, $password)
        {
            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $users = $wfs->selectCollection('users');
            $users->ensureIndex(array("username" => 1), array("unique" => 1));

            $login_query = $users->findOne(array("username" => $username, "password" => $password));

            print_r($login_query);
            
            if(is_null($login_query))
            {
                return array("response" => "fail");
            }
            else
            {
                return array("response" => 'ok');
            }


        }    

        public function roll_dice()
        {
        }

        

        public function nearby_venues($lat, $lng, $username)
        {
            require_once('../../../secret.php');
            $foursquare = new FoursquareAPI(CLIENT_ID, CLIENT_SECRET);

            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $nearby_venues = $wfs->selectCollection('nearby');

            //clear previous searches no need to archive
            //lazily erasing previous searches allows for last search to be kept on file
            //may need to erase after each use...
            try
            {
                $nearby_venues->remove(array('username' => $username));
            }
            catch (MongoCursorException $e)
            {
                #nop
            }

            //prepare default params
            $params = array('ll' => "$lat, $lng", 'radius' => 2000);

            // Perform a request to a public resource
            $response = $foursquare->GetPublic("venues/search",$params);
            $venues = json_decode($response);


            $final_insert_array= array();
            $insert_array = array();

            #un-nest the response
            foreach($venues->response->venues as $venue)
            {
                foreach($venue as $key => $value)
                {
                    if (is_object($key) or is_object($value))
                    {
                        foreach($value as $nested_key => $nested_value)
                        {
                            if (!is_object($nested_value))
                            {
                                $insert_array[$nested_key] = $nested_value;
                            }
                        }
                    }
                    else
                    {

                        $insert_array[$key] = $value;

                    }
                }
                array_push($final_insert_array, $insert_array);
            }

            try
            {
                $nearby_venues->insert(array('username' => $username, 'nearby' => $final_insert_array));

            }
            catch(MongoCursorException $e)
            {
                return array('response' => 'fail', 'reason' => 'insert');
            }
            /*
            > db.nearby.aggregate( {$unwind: "$nearby"},
                                   {$sort: {'nearby.checkinsCount':-1}},
                                   {$limit:5},
                                   {$project:
                                        {'nearby.name':1,
                                         'nearby.checkinsCount':1,
                                         "nearby.id":1 ,
                                         "nearby.distance":1}} )

            */

            try
            {
                $agg_array = array(array('$unwind' => '$nearby'),
                                   array('$match' => array('username' => $username)),
                                   array('$sort' => array('nearby.checkinsCount' => -1)),
                                   array('$limit' => 5),
                                   array('$project' => array('nearby.name' => 1,
                                                             'nearby.checkinsCount' => 1,
                                                             'nearby.id' =>   1,
                                                             'nearby.distance' => 1
                                   ))
                );

                $aggregate = $nearby_venues->aggregate( $agg_array );

                return array('response' => 'ok', 'top5' => $aggregate);
            }
            catch(MongoCursorException $e)
            {
                return array('response' => 'fail', 'reason' => 'top5');
            }


        }


    } //end WarFareSquare class

$rest = new RestServer();
$rest->addServiceClass('WarFareSquare');
$rest->handle();
