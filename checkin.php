<?php

# https://github.com/jakesankey/PHP-RestServer-Class/blob/master/RestServer.php
require_once('RestServer.php');

/**
 * Class WarFareSquare
 *
 * A class that holds api methods which serves to decouple the application logic
 * from the application implementation(s) and platform(s).
 *
 * Serves json strings back to whomever calls the api methods
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
     */
    public function checkin($id, $username)
    {
        $testing = true;  $foursquare = $venues_db = null;
        include('mongo_setup_venues.php'); include('foursquare_setup.php');

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
