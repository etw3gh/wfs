<?php
    require_once("FoursquareAPI.class.php");
    require_once('RestServer.php');

    /**
     * Class WarFareSquare
     * 
     * A class that holds api methods which serves to decouple the application logic
     * from the application implementation(s) and platform(s). 
     *
     * Serves json strings back to whomever calls the api methods   
     * 
     * sample api call:
     * http://wfs.openciti.ca?method=MethodName&param1=Param1Value&param2=Param2Value
     *
     * IMPORTANT: convert all data to string before db ops
     *
     * @TODO add wfs api key to all urls and methods to prevent gaming the system
     *
     * @TODO harmonize all responses to: response : ok/fail , reason : 'blah ...'
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
         * @param string $full_response
         * @return array|null
         *
         * IMPORTANT:  please md5 encode the password
         */
        public function register_user($username, $password, $first, $last, $full_response='false')
        {
            //database setup
            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $users = $wfs->selectCollection('users');
            $users->ensureIndex(array("username" => 1), array("unique" => 1));

            $insert_array = null;

            try
            {
                $my_venues = array('id' => '',
                                   'soldiers_placed' => 0);


                $insert_array = array('username' => (string) $username,
                                      'password' => (string) $password,
                                      'first' => (string) $first,
                                      'last' => (string) $last,
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
         * @param $id unique foursquare id for the venue the user wishes to check into
         * @param $username unique wfs username
         *
         * checks a warfaresquare user to a venue
         * according to the foursquare id of that venue
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

            //database setup
            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $venues_db = $wfs->selectCollection('venues');
            $venues_db->ensureIndex(array('id' => 1), array('unique' => 1));

            //return fail on bad return code from foursquare
            if ( (int) $the_venue->meta->code != 200)
            {
                return array('response' => 'fail');
            }

            //construct associative array from foursquare response for db insertion
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
                                                  'other_stuff' => 'to be determined'));
                }
                else
                {
                    //perform update if venue exists
                    //pushes player onto player list
                    $venues_db->update(array('id' => $id),
                                       array('$push' => array('players' => $username)));
                }
            }
            catch (MongoCursorException $e)
            {
                return array('response' => 'fail', 'reason' => $e->getMessage());
            }
            catch (MongoException $e)
            {
                return array('response' => 'fail', 'reason' => $e->getMessage());
            }
            return array('response' => 'ok');
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
         * Logs in an existing warfoursquare user to the server 
         *
         * IMPORTANT:   remember to md5 encode password
         */
        public function login_user($username, $password)
        {
            //database setup
            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $users = $wfs->selectCollection('users');
            $users->ensureIndex(array("username" => 1), array("unique" => 1));

            //calculate the seconds in a day for soldier calculations
            $seconds_per_day = 24 * 60 * 60;

            //possibly add as field in user document
            $soldiers_per_day = 1;

            //query database for the user
            $login_query = $users->findOne(array("username" => (string) $username,
                                                 "password" => (string) $password));

            //return 'fail' response if either username or password or both are incorrect or not found
            if(is_null($login_query))
            {
                return array('response' => 'fail');
            }
            //log the user in and see if they deserve a soldier
            else
            {
                //give the user his soldier for the day
                //check to see if it has already not been given

                $time_since_last_soldier = $login_query['last_daily_soldier'] - date('U');

                if ($time_since_last_soldier > $seconds_per_day)
                {                    
                    try
                    {
                        //issue new soldier to user and update the last_daily_soldier field
                        $users->update(array('username'=> (string) $username),
                                       array('$inc' => array('soldiers' => $soldiers_per_day)),
                                       array('$set' => array('last_daily_soldier' => date('U'))));
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
         * method to return $how_many venues with the highest number of foursquare checkins
         *
         * grabs json from foursquare and performs aggregations on the results in mongodb
         * presents nicely sorted (by foursquare checkins) json array to the calling application
         * and only includes a few important details of the original response 
         *
         * @param $lat
         * @param $lng
         * @param $username
         * @param int $how_many
         * @return array
         */
        public function nearby_venues($lat, $lng, $username, $how_many)
        {
            require_once('../../../secret.php');
            $foursquare = new FoursquareAPI(CLIENT_ID, CLIENT_SECRET);

            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $nearby_venues = $wfs->selectCollection('nearby');

            //nearby venues not stored so delete entire collection before each use
            try
            {
                $nearby_venues->remove(array());
            }
            catch (MongoException $e)
            {
                #nop
            }

            //prepare default params
            //TODO: determine if radius is sufficient and/or increase upon low results
            $params = array('ll' => "$lat, $lng", 'radius' => 2000);

            //Perform a request to a public resource
            $response = $foursquare->GetPublic("venues/search",$params);
            $venues = json_decode($response);

            //prep mongo db document
            $nearby_venues->insert(array('user' => $username, 'venues' => array() ));

            //push relevant venue details to doc
            foreach ($venues->response->venues as $v)
            {
                $insert_array =array();
                $insert_array['id'] = $v->id;
                $insert_array['name'] = $v->name;
                $insert_array['distance'] = $v->location->distance;
                $insert_array['checkins'] = $v->stats->checkinsCount;

                try
                {
                    $nearby_venues->update(array('user' => $username),
                        array('$push' => array('venues' => $insert_array )));
                }
                catch (MongoCursorException $e)
                {
                    return array('response' => 'fail', 'reason' => $e->getMessage());
                }
                catch (MongoException $e)
                {
                    return array('response' => 'fail', 'reason' => 'other database error');
                }
            }
            
            try
            {
                /*
                    AGGREGATION PIPELINE
                    $unwind: used to access nested array 'venues'
                    $sort: -1 used for descending order sort of venues[checkins]
                    $limit: returns only however many the function is asked for ($how_many)
                    $project: create projection of 'columns' to exclude (0) mongodb _id 
                              and include (1) the venues array    
                */
                $agg_array = array( array('$unwind' => '$venues'),                      
                                    array('$sort' => array('venues.checkins' =>-1 )),
                                    array('$limit' => (int) $how_many),
                                    array('$project' =>
                                            array('_id' => 0,
                                                'venues' => 1)));
                //perform the aggregation
                $aggregate = $nearby_venues->aggregate( $agg_array );

                //return the aggregation as the value for key 'top_venues'
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
