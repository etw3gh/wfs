<?php
    # https://github.com/jakesankey/PHP-RestServer-Class/blob/master/RestServer.php
    require_once('RestServer.php');

    /**
     * DEPRECIATED USE THESE ENDPOINTS INSTEAD:
     *
     * admin.php
     * nearby.php
     * checkin.php
     * soldiers.php
     *
     */
class WarFareSquare
    {

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
        public function register_user($username, $password, $first, $last, $full_response)
        {
            $users = null; include('mongo_setup_users.php');

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
                return array('response' => 'fail', 'reason' => 'duplicate user');
            }
            catch(MongoException $e)
            {
                return array('response' => 'fail');
            }

            if(strtolower($full_response) == 'true' )
            {
                $insert_array['response'] = (string) $return_code;
                return $insert_array;
            }
            else
            {
                return array('response' => (string) $return_code);
            }
        }

        /**
         * @param $id string unique foursquare id for the venue the user wishes to check into
         * @param $username string unique wfs username
         *
         * Method to CHECKIN warfaresquare user to a venue
         * according to the foursquare id of that venue
         *
         * Method to CREATE A VENUE on the server if this is the first ever checkin
         * Method to MODIFY A VENUE if the venue already exists on the server
         *
         * @return array
         */
        public function wfs_checkin($id, $username)
        {
            $testing = true;  $foursquare = $venues_db = null;
            include('mongo_setup_venues.php');   include('foursquare_setup.php');

            //OBTAIN A VENUE BY VENUE ID
            $response = $foursquare->GetPublic("venues/$id");
            $the_venue = json_decode($response);


            //return fail on bad return code from foursquare
            if ( (int) $the_venue->meta->code != 200)
            {
                return array('response' => 'fail', 'reason' => $the_venue->meta->code);
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

            //the mayor is always the owner of the soldiers
            $insert_array['soldiers'] = 0;
            $insert_array['daily_soldiers'] = 0;
            $insert_array['daily_soldiers_added_on'] = date('U'); #check field in cron_venues.php
            $insert_array['daily_soldiers_removed_on'] = '';
            $insert_array['mayor'] = '';
            #$insert_array[''] = 0;
            //only used for new venue
            $insert_array['players'] = array($username);

            try
            {
                //check to see if venue exists (ie: min 1 wfs checkin)
                $exists_query = $venues_db->findOne(array('id' => $id));

                if (is_null($exists_query))
                {
                    //perform insert if no venue exists
                    $venues_db->insert($insert_array);

                    return array('response' => 'ok',
                                 'stats' => array('opponent' => $exists_query['soldiers']['owner'],
                                                  'troops' => $exists_query['soldiers']['number'],
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
         * @param $id           string representing unique foursquare id
         * @param $username     string respresenting unique warfoursquare username
         *
         * method to check a user out of a venue
         * simply pulls their username from venues 'players' array
         *
         * assume a player may be at only one location at any given time
         *
         * @TODO determine what game stats need preserving on the server
         *
         * @return array
         */
        public function wfs_checkout($id, $username)
        {
            $venues_db = null; include('mongo_setup_venues.php');

            try
            {
                $venues_db->update(array('id' => $id),
                                   array('$pull' => array('players' => $username)));
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
         * @param $password
         * @return array
         *
         * Logs in an existing warfoursquare user to the server 
         *
         * IMPORTANT:   remember to md5 encode password
         */
        public function login_user($username, $password)
        {
            $users = null; include('mongo_setup_users.php');

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
         * creates a temporary aggregation table with temp_id = date('U')
         * table is erased immediately after aggregation is performed
         *
         * @param $lat
         * @param $lng
         * @param $username
         * @param $how_many
         * @param $restrict_categories string (true or false)
         *
         * @return array
         *
         * @TODO radius currently 2000M - allow app to modify
         */
        public function nearby_venues($lat, $lng, $username, $how_many, $restrict_categories)
        {
            $foursquare = $venues_db = $nearby_venues = $wfs = null;
            include('mongo_setup_venues.php');include('foursquare_setup.php');
            $nearby_venues = $wfs->selectCollection('nearby');

            //TODO: determine if radius is sufficient and/or increase upon low results
            $radius = 2000;

            //add or omit category restrictions
            if (strtolower($restrict_categories) == 'true')
            {
                //prepare foursquare categories
                $food_4s_id = '4d4b7105d754a06374d81259';
                $arts_4s_id = '4d4b7104d754a06370d81259';
                $bar_4s_id  = '4d4b7105d754a06376d81259';
                $shopping_4s_id = '4d4b7105d754a06378d81259';
                $travel_4s_id = '4d4b7105d754a06379d81259';
                $categories = $food_4s_id . ',' . $arts_4s_id . ',' . $bar_4s_id . ',' . $shopping_4s_id . ',' . $travel_4s_id;

                //prepare default params with categories selected
                $params = array('ll' => "$lat, $lng",
                                'radius' => $radius,
                                'categories' => $categories);
            }
            else
            {
                //prepare default params without categories selected
                $params = array('ll' => "$lat, $lng", 'radius' => $radius);
            }

            //Perform a request to a public resource
            $response = $foursquare->GetPublic("venues/search",$params);
            $venues = json_decode($response);

            //unique timestamp to label temporary document or 'table' so it can be erased
            $temp_id = date('U');
            //prep mongo db document
            $nearby_venues->insert(array('temp_id' => $temp_id, 'venues' => array() ));


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
                    $nearby_venues->update(array('temp_id' => $temp_id),
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
                    $match: restrict to the current temp_id just in case more exist
                    $sort: -1 used for descending order sort of venues[checkins]
                    $limit: returns only however many the function is asked for ($how_many)
                    $project: create projection of 'columns' to exclude (0) mongodb _id 
                              and include (1) the venues array    
                */
                $agg_array = array( array('$unwind' => '$venues'),
                                    array('$match' => array('temp_id' => $temp_id)),
                                    array('$sort' => array('venues.checkins' =>-1 )),
                                    array('$limit' => (int) $how_many),
                                    array('$project' =>
                                            array('_id' => 0,
                                                'venues' => 1)));
                //perform the aggregation
                $aggregate = $nearby_venues->aggregate( $agg_array );

                //attempt to delete the temporary document (no big deal if it fails)
                try
                {
                    $nearby_venues->remove(array('temp_id' => $temp_id));
                }
                catch (MongoCursorException $e){
                    //nop
                }

                //return the aggregation as the value for key 'top_venues'
                return array('response' => 'ok', 'top_venues' => $aggregate);
            }
            catch(MongoCursorException $e)
            {
                return array('response' => 'fail', 'reason' => 'aggregation');
            }
        }

        //Gaming Methods-------------------------------------------------------------------

        /**
         * method to allow the venue's controlling user to pickup the soldier.
         *
         * @param $id       string    unique foursquare venue id
         * @param $username string    unique warfoursquare username
         *
         * @return array
         *
         * @TODO try catch around mongodb operations
         *
         */
        public function pickup_soldiers($id, $username)
        {
            $venues_db = null; $users_db = null;
            include('mongo_setup_venues_and_users.php');

            //determine if our user is the mayor of location supplied by $id
            $is_mayor_query = $venues_db->findOne(array('mayor' => $username, 'id' => $id));

            if(!is_null($is_mayor_query))
            {
                $soldiers_available = $is_mayor_query['daily_soldiers'];
                if ($soldiers_available > 0)
                {
                    //first add to user
                    $users_db->update(array('username' => $username),
                                      array('$inc' => array('soldiers' => $soldiers_available)));

                    //remove from venue (sets field to zero
                    //TODO determine if soldier related timestamps need altering
                    $venues_db->update(array('id' => $id),
                                       array('$set' => array('daily_soldiers' => 0,
                                                             'daily_soldiers_removed_on' => date('U'))));
                }
                else
                {
                    return array('response' => 'fail', 'reason' => 'no soldiers');
                }
            }
            else
            {
                return array('response' => 'fail', 'reason' => 'user not mayor');
            }

            //TODO return stats . . .
            return array('response' => 'ok');
        }

        /**
         * Method that allows a venue's controlling user to place defending soldiers
         *
         * @param $id       string    unique foursquare venue id
         * @param $username string    unique warfoursquare username
         * @param $number   string of an integer representing the number of soldiers to place
         * @return array
         *
         * @TODO try / catch around mongodb operations
         */
        public function place_soldiers($id, $username, $number)
        {
            $venues_db = null; $users_db = null;
            include('mongo_setup_venues_and_users.php');

            //determine if our user is the mayor of location supplied by $id
            $is_mayor_query = $venues_db->findOne(array('mayor' => $username, 'id' => $id));

            if(is_null($is_mayor_query))
            {
                return array('response' => 'fail', 'reason' => 'user not mayor');
            }

            //prepare return array
            $success_array = array('response' => 'ok');

            //according to gdd maximum 10 dice / soldiers per venue
            $max_placement = 10;

            $user_query = $users_db->findOne(array('username' => $username));

            if (is_null($user_query))
            {
                return array('response' => 'fail', 'reason' => 'invalid user');
            }

            //now we know the user is the mayor and that the user exists

            //retrieve the number of soldiers already defending the location
            //by definition, they will belong to this user and count towards the max
            $soldiers_already_defending = $is_mayor_query['soldiers'];
            $user_able_to_place = $max_placement - $soldiers_already_defending;

            //retrieve the actual number of soldiers the user has available to them
            $actual_number_of_soldiers = $user_query['soldiers'];

            if($number > $actual_number_of_soldiers)
            {
                $success_array['warning'] = "$number requested, $actual_number_of_soldiers placed";
                $number = $actual_number_of_soldiers;

                //account for case where user wishes to deploy more than the max number allowed
                if ($number > $user_able_to_place)
                {
                    $success_array['warning'] = "$number requested, $user_able_to_place placed";
                    $number = $user_able_to_place;
                }
            }

            //now place $number of soldiers at the location and remove -$number from the user
            //mongo has no '$dec' operator...
            $reduce_by = $number * (-1);
            $users_db->update(array('username' => $username),
                              array('$inc' => array('soldiers' => $reduce_by)));

            $venues_db->update(array('mayor' =>$username),
                               array('$inc' => array('soldiers' => $number)));


            $success_array['stats'] = array('usersoldiers' => $actual_number_of_soldiers - $number,
                                            'venuesolders' => $soldiers_already_defending + $number);
            return $success_array;
        }

        public function roll_dice()
        {
        }




    } //end WarFareSquare class

$rest = new RestServer();
$rest->addServiceClass('WarFareSquare');
$rest->handle();
