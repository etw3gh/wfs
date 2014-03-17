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
     * @param $id          string    unique foursquare venue id
     * @param $username    string    unique warfoursquare username of attacking user
     * @param $attackers   int       how many attacking soldiers to use
     * @param $leavebehind int       how many to leave behind upon success (saves calls)
     *
     * @return array
     *
     * @TODO try catch around mongodb operations
     *
     * SAMPLE USER DOCUMENT
     *
     * > db.users.findOne()
        {
            "_id" : ObjectId("53226eb03a3cad98468b4571"),
            "username" : "iamsabbath",
            "password" : "666",
            "first" : "Toni",
            "last" : "Iommi",
            "soldiers" : 0,
            "last_daily_soldier" : "1394765488",
            "lat" : null,
            "lng" : null
        }
     *
     * SAMPLE VENUE DOCUMENT
     *
     * > db.venues.findOne()
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
    public function attack($id, $username, $attackers, $leavebehind)
    {
        $venues_db = null; $users = null;
        include('mongo_setup_venues_and_users.php');

        # get venue details
        $venue_query = $venues_db->findOne(array('id' => $id));

        # short circuit 1 - bad venue
        if(is_null($venue_query))
        {
            return array('response' => 'fail', 'reason' => 'invalid venue');
        }

        # short circuit 2 - no mayor
        # TODO should we make this user the mayor???
        if($venue_query['mayor'] == '' or is_null($venue_query['mayor']))
        {
            return array('response' => 'fail', 'reason' => 'no mayor to attack');
        }

        # get attacker details
        $attacker_query = $users->findOne(array('username' => $username));

        # short circuit 3 - invalid attacker
        if (is_null($attacker_query))
        {
            return array('response' => 'fail', 'reason' => 'invalid attacker');
        }

        # get actual number of soldiers the attacker has
        $attack_with = null;
        $soldiers_available = $attacker_query['soldiers'];

        # short circuit 4 - the attacker has insufficient soldiers
        if ($soldiers_available <= 0 or is_null($soldiers_available))
        {
            return array('response' => 'fail', 'reason' => 'insufficient soldiers');
        }

        # short circuit 5 - not defended, default win for attacker
        if($venue_query['defenders'] <= 0 or is_null($venue_query['defenders']))
        {


        }


        # adjust attack level to what is actually available to the attacker
        if ($attackers > $soldiers_available)
        {
            $attack_with = $soldiers_available;
        }
        else
        {
            $attack_with = $attackers;
        }

        # keep attacking number within limit set by game design document (10)
        if ($attack_with > 10)
        {
            $attack_with = 10;
        }

            $users->update(array('username' => $username),
                           array('$inc' => array('soldiers' => $soldiers_available)));

            # remove from venue (sets field to zero
            # TODO determine if soldier related timestamps need altering
            $venues_db->update(array('id' => $id),
                array('$set' => array('soldiers' => 0,
                    'soldiers_removed_on' => date('U'))));





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
