<?php

# https://github.com/jakesankey/PHP-RestServer-Class/blob/master/RestServer.php
require_once('RestServer.php');

/**
 * Class WarFareSquare
 */

class WFS_Attack
{
    /**
     * method to allow the venue's controlling user to attack a position
     *
     * @param $id       string    unique foursquare venue id
     * @param $username string    unique warfoursquare username of attacking user
     * @param $attackers int how many attacking soldiers to use
     *
     * @return array
     *
     * @TODO try catch around mongodb operations
     *
     * SAMPLE VENUE DOCUMENT
     *
     * > db.venues.find()
     *   { "_id" : ObjectId("53226eb93a3cad98468b4577"),
     *     "checkins" : 623,
     *     "defenders" : 2,
     *     "id" : "4b11a4c8f964a5203c8123e3",
     *     "lat" : 43.665515,
     *     "lng" : -79.46983805,
     *     "mayor" : "iamsabbath",
     *     "name" : "Vesuvio's Pizzeria & Spaghetti House",
     *     "players" : [  "iamsabbath",  "sabbathdrummer" ],
     *     "soldier_added_on" : "1394765497",
     *     "soldier_removed_on" : "1394765497",
     *     "soldiers" : 0 }
     *
     */
    public function attack($id, $username, $attackers)
    {
        $venues_db = null; $users = null;
        include('mongo_setup_venues_and_users.php');

        # determine if our user is the mayor of location supplied by $id
        $venue_query = $venues_db->findOne(array('mayor' => $username, 'id' => $id));

        if(is_null($venue_query))
        {
            return array('response' => 'fail', 'reason' => 'no mayor to attack');
        }

        #setup defender



        #setup attacker



        # TODO return stats . . .
        return array('response' => 'ok');
    }

}

###################### MAIN

$rest = new RestServer();
$rest->addServiceClass('WFS_Attack');
$rest->handle();
