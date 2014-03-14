<?php

# https://github.com/jakesankey/PHP-RestServer-Class/blob/master/RestServer.php
require_once('RestServer.php');

/**
 * Class WarFareSquare
 */

class WFS_Checkin
{
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
     *
     * @todo oauth stuff
     */
    public function checkin($id, $username)
    {
        # setup & initialize foursquare api and mongodb connections
        $foursquare = $users = $venues_db = null;
        include('mongo_setup_venues_and_users.php');
        include('foursquare_setup.php');

        # OBTAIN A VENUE BY VENUE ID
        $response = $foursquare->GetPublic("venues/$id");
        $the_venue = json_decode($response);


        # return fail on bad return code from foursquare
        if ( (int) $the_venue->meta->code != 200)
        {
            return array('response' => 'fail', 'reason' => $the_venue->meta->code);
        }

        #check to see if user is already checked in
        try
        {
            $login_check = $venues_db->find(array('id' => $id, 'players' => array('$in' => [$username])));

            if($login_check->count() != 0)
            {
                return array('response' => 'ok', 'warning' => 'checked in');
            }
        }
        catch (MongoCursorException $e)
        {
            return array('response' => 'warning', 'reason' => $e->getMessage());
        }
        catch (MongoException $e)
        {
            return array('response' => 'warning', 'reason' => $e->getMessage());
        }

        try
        {
            # check to see if venue exists (ie: min 1 wfs checkin)
            $exists_query = $venues_db->findOne(array('id' => $id));

            if (is_null($exists_query))
            {
                $the_date = date('U');

                # construct associative array from foursquare response for db insertion
                $insert_array = array();
                $insert_array['id'] = $the_venue->response->venue->id;
                $insert_array['name'] = $the_venue->response->venue->name;
                $insert_array['lat'] = $the_venue->response->venue->location->lat;
                $insert_array['lng'] = $the_venue->response->venue->location->lng;
                $insert_array['checkins'] = $the_venue->response->venue->stats->checkinsCount;

                # the mayor is awarded the soldier so 1-1=0
                $insert_array['soldiers'] = 0;

                $insert_array['defenders'] = 0;


                $insert_array['soldier_added_on'] = $the_date;
                $insert_array['soldier_removed_on'] = $the_date;
                $insert_array['mayor'] = $username;

                # only used for new venue
                $insert_array['players'] = array($username);

                # perform insert
                $venues_db->insert($insert_array);

                # give the user the soldier
                $users->update(array('username' => $username),
                               array('$inc' => array('soldiers' => 1)));

                #verify venue stats and return to caller
                #perform query
                $verify_venue = $venues_db->findOne(array('id'=> $insert_array['id']),
                                                    array('_id' => 0));

                if (!is_null($verify_venue))
                {
                    $verify_venue['response'] = 'ok';
                    return $verify_venue;
                }
                {
                    return array('response' => 'fail', 'reason' => 'venue not found');
                }
            }

            #venue exists and user is not checked in
            else
            {
                # check to see if there is already a mayor
                # if not make this user the mayor
                if (!is_null($exists_query['mayor']))
                {
                    # perform update if venue exists
                    # pushes player onto player list
                    $venues_db->update(array('id' => $id),
                                       array('$push' => array('players' => $username)));
                }
                else
                {
                    # perform update if venue exists
                    # pushes player onto player list
                    # make this player the mayor
                    $venues_db->update(array('id' => $id),
                                       array('$set' => array('mayor' => $username)),
                                       array('$push' => array('players' => $username)));

                    # assign soldier (if available) to this user
                    if ($exists_query['soldiers'] > 0)
                    {
                        $users->update(array('username' => $username),
                                       array('$inc' =>
                                             array('soldiers' => (int) $exists_query['soldiers'])));
                    }
                }

                return (array('response' => 'ok'));

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
    }


    /**
     * @param $id           string representing unique foursquare id
     * @param $username     string representing unique warfoursquare username
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
    public function checkout($id, $username)
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
}

###################### MAIN
$rest = new RestServer();
$rest->addServiceClass('WFS_Checkin');
$rest->handle();
