<?php
    require_once("FoursquareAPI.class.php");
    require_once('RestServer.php');

    /**
     * Class WarFareSquare
     *
     * http://wfs.openciti.ca?method=MethodName&param1=Param1Value&param2=Param2Value
     *
     * IMPORTANT: convert all data to string before db ops
     *
     * @TODO add wfs api key to all urls and methods to prevent gaming the system
     *
     * @TODO refactoring of Foursquare responses
     *
     */
    class WarFareSquare
    {
        //example of a function
        public function hello($name){return(array("response" => "Hello $name"));}

        /**
         * @param $username
         * @param $password
         * @param $first
         * @param $last
         *
         * LAT AND LONG MAY BE ELIMINATED FROM THIS METHOD
         * @param null $lat
         * @param null $lng
         *
         *
         * @param string $full_response
         * @return array|null
         *
         * IMPORTANT:  please md5 encode the password
         */
        public function register_user($username, $password, $first, $last, $lat=null, $lng=null, $full_response='false') 
        {
            //must be repeated for each api endpoint function due to RestServer functionality
            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $users = $wfs->selectCollection('users');
            $users->ensureIndex(array("username" => 1), array("unique" => 1));

            $insert_array = null;

            try{
                $my_venues = array('id' => '',
                                   'soldiers_placed' => 0,



                );


                $insert_array = array('username' => (string) $username,
                                      'password' => (string) $password,
                                      'first' => (string) $first,
                                      'last' => (string) $last,
                                      'lat' => (string) $lat,
                                      'lng' => (string) $lng,
                                      'soldiers' => 1,
                                      'last_daily_soldier' => date('U'),
                                      'venues' => $my_venues);

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


        /**
         * @param $id unique foursquare id
         * @param $username unique wfs username
         *
         * checks a user in according to foursquare id
         *
         * @return array
         */
        public function wfs_checkin($id, $username)
        {
            $testing = true;

            //Foursquare setup
            require_once('../../../secret.php');
            $foursquare = new FoursquareAPI(CLIENT_ID, CLIENT_SECRET);
            //OBTAIN A VENUE BY VENUE ID
            $response = $foursquare->GetPublic("venues/$id");
            $the_venue = json_decode($response);

            //DB setup
            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $venues_db = $wfs->selectCollection('venues');
            $venues_db->ensureIndex(array('id' => 1), array('unique' => 1));

            //return fail on bad return code from foursquare
            if ( (int) $the_venue->meta->code != 200)
                return array('response' => 'fail');

            //construct associative array for db insertion
            $insert_array = array();
            $insert_array['id'] = $the_venue->response->venue->id;
            $insert_array['name'] = $the_venue->response->venue->name;
            $insert_array['lat'] = $the_venue->response->venue->location->lat;
            $insert_array['lng'] = $the_venue->response->venue->location->lng;
            $insert_array['checkins'] = $the_venue->response->venue->stats->checkinsCount;
            $insert_array['users'] = $the_venue->response->venue->stats->usersCount;
            $insert_array['tips'] = $the_venue->response->venue->stats->tipCount;
            //construct wfs attributes
            $insert_array['soldiers'] = 0;
            $insert_array['added_on'] = date('U');
            $insert_array['mayor'] = '';
            //only used for new venue
            $insert_array['players'] = array($username);

            if ($testing)
            {
                print "CODE:         " . $insert_array['code'] . "<br />";
                print "FourSqure ID: " .  $insert_array['id']  . "<br />";
                print "Name:         " . $insert_array['name'] . "<br />";
                print "Lat:          " . $insert_array['lat'] . "<br />";
                print "Lng:          " . $insert_array['lng'] . "<br />";
                print "Checkins Count: " . $insert_array['checkins'] . "<br />";
                print "Users Count: " . $insert_array['users'] . "<br />";
                print "Tip Count: " . $insert_array['tips'] . "<br />";
            }

            try
            {
                //check to see if venue exists (ie: min 1 wfs checkin)
                $exists_query = $venues_db->findOne(array('id' => $id));
                if (is_null($exists_query))
                {
                    //perform insert if no venue exists
                    $venues_db->insert($insert_array);
                    return array('response' => 'ok',
                                 'stats' => array('soldiers' => $exists_query['soldiers'],
                                                  'mayor' => $exists_query['mayor'],
                                                  'other_stuff' => 'to be determined',
                                 ));
                }
                else
                {
                    //perform update if venue exists
                    //pushes player onto player list
                    $venues_db->update(array('id' => $id),
                                       array('$push' => array('players' => $username))
                    );
                }
            }
            catch (MongoCursorException $e)
            {
                print $e->getMessage();
                return array('response' => 'fail', 'reason' => 'MongoCursor');
            }
            catch (MongoException $e)
            {
                print $e->getMessage();
                return array('response' => 'fail', 'reason' => 'Mongo');
            }


            return array('response' => 'still testing');


        }


        /**
         * @param $username
         * assume a player may be at only one location at any given time
         * thus to checkout we just need the username
         */
        public function wfs_checkout($username)
        {


        }




        /**
         * @param $username
         * @param $password
         * @return array
         *
         * IMPORTANT:   remember to md5 encode password
         */
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






        /**
         * api method to return $how_many venues with the highest number of 4s checkins
         *
         * grabs json from foursquare and performs aggregations on the results in mongodb
         *
         *
         *
         * @param $lat
         * @param $lng
         * @param $username
         * @param int $how_many
         * @return array
         */
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
                return array('response' => 'fail', 'reason' => 'nearby_venues');
            }


        }



        //Gaming Methods-------------------------------------------------------------------


        public function roll_dice()
        {
        }




    } //end WarFareSquare class

$rest = new RestServer();
$rest->addServiceClass('WarFareSquare');
$rest->handle();
