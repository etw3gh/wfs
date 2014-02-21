<?php
    require_once("FoursquareAPI.class.php");
    require_once('RestServer.php');

    //important: convert all data to string before db ops

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
                $insert_array = array('username' => (string) $username,
                                      'password' => (string) $password,
                                      'first' => (string) $first,
                                      'last' => (string) $last,
                                      'lat' => (string) $lat,
                                      'lng' => (string) $lng,
                                      'soldiers' => 1,
                                      'last_daily_soldier' => date('U'),
                                      'venues' => array());

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
                $insert_array["response"] = (string) $return_code;
                return $insert_array;            
            }
            else
            {
                return array("response" => (string) $return_code);
            }
        }
 
        //remember to md5 encode password
        public function login_user($username, $password)
        {
            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $users = $wfs->selectCollection('users');
            $users->ensureIndex(array("username" => 1), array("unique" => 1));

            $seconds_per_day = 24 * 60 * 60;

            #possibly add as field in user document
            $soldiers_per_day = 1;

            $login_query = $users->findOne(array("username" => (string) $username,
                                                 "password" => (string) $password));

            if(is_null($login_query))
            {
                return array('response' => 'fail');
            }
            else
            {
                #give the user his soldier for the day
                #check to see if it has already not been given

                $time_since_last_soldier = $login_query['last_daily_soldier'] - date('U');

                if ($time_since_last_soldier > $seconds_per_day)
                {
                    #issue new soldier to user and update the last_daily_soldier field
                    #db.users.update({username: 'bob1'},{$inc: {soldiers:1 }})
                    try
                    {
                        $users->update(array('username'=> (string) $username),
                                   array('$inc' => array('soldiers' => $soldiers_per_day)),
                                   array('$set' => array('last_daily_soldier' => date('U')))
                        );
                    }
                    catch(MongoCursorException $e)
                    {
                        return array('response' => 'ok', 'daily_soldier' => 'fail');
                    }
                }
                return array('response' => 'ok');
            }
        }    

        public function roll_dice()
        {



        }

        

        public function nearby_venues($lat, $lng, $username, $how_many=5)
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
                $nearby_venues->remove(array('username' => (string) $username));
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


            $final_insert_array=  array();

            #un-nest the response (a bit cleaner than pumping in json_decode($x, true)
            foreach($venues->response->venues as $venue)
            {
                $insert_array = array();
                foreach($venue as $key => $value)
                {
                    if (is_object($key) or is_object($value))
                    {
                        foreach($value as $nested_key => $nested_value)
                        {
                            if (!is_object($nested_value))
                            {
                                $insert_array[$nested_key] = (string) $nested_value;
                            }
                        }
                    }
                    else
                    {
                        $insert_array[$key] = (string) $value;
                    }
                }
                array_push($final_insert_array, $insert_array);
            }

            try
            {
                $nearby_venues->insert(array('username' => (string) $username, 'nearby' => $final_insert_array));

            }
            catch(MongoCursorException $e)
            {
                return array('response' => 'fail', 'reason' => 'insert');
            }

            //get top venues according to checkinsCount.
            try
            {
                $agg_array = array(array('$unwind' => '$nearby'),
                                   array('$match' => array('username' => (string) $username)),
                                   array('$sort' => array('result.nearby.checkinsCount' => -1)),
                                   array('$limit' => (int) $how_many),
                                   array('$project' => array('nearby.name' => 1,
                                                             'nearby.checkinsCount' => 1,
                                                             'nearby.id' =>   1,
                                                             'nearby.distance' => 1,
                                                             '_id' => 0
                                   )));

                $aggregate = $nearby_venues->aggregate( $agg_array );

                return array('response' => 'ok', 'top_venues' => $aggregate);
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
