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
            
            //-------------------------------------------------------------------------------

            $return_code = 'null';
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

        

        public function nearby_venues($lat, $lng, $limit=30, $query=null, $radius=1000)
        {
            require_once('../../../secret.php');
            $foursquare = new FoursquareAPI(CLIENT_ID, CLIENT_SECRET);

            //prepare default params
            $param_array = array('ll' => "$lat, $lng", 'limit' => $limit, 'radius' => $radius);

            //add query if user or app demands it
            if (!is_null($query))
            {
                $param_array['query'] = $query;
            }

            $params =  $param_array;

            // Perform a request to a public resource
            $response = $foursquare->GetPublic("venues/search",$params);
            $venues = json_decode($response);



        }


    } //end WarFareSquare class

$rest = new RestServer();
$rest->addServiceClass('WarFareSquare');
$rest->handle();
