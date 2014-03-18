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
     * @TODO check  last_attacked_on and last_attacked_by from venue
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
        $venues_db = $users = null;
        include('mongo_setup_venues_and_users.php');

        # get venue details
        $venue_query = $venues_db->findOne(array('id' => $id));

        # short circuit - bad venue
        if(is_null($venue_query))
        {
            return array('response' => 'fail', 'reason' => 'invalid venue');
        }

        # save the date
        $the_date = date('U');

        # short circuit - re-attack too within 12 hours
        $last_attacked_on = $venue_query['last_attacked_on'];
        $last_attacked_by  = $venue_query['last_attacked_by'];
        $seconds_in_12_hours = 12 * 60 * 60;
        $time_since_last_attack =  - $the_date;

        if ($last_attacked_on )


        # save current mayor into local alias
        $current_mayor = $venue_query['mayor'];

        # short circuit - no mayor
        # TODO should we make this user the mayor???
        if($current_mayor == '' or is_null($current_mayor))
        {
            return array('response' => 'fail', 'reason' => 'no mayor to attack');
        }



        # get attacker details
        $attacker_query = $users->findOne(array('username' => $username));

        # short circuit - invalid attacker
        if (is_null($attacker_query))
        {
            return array('response' => 'fail', 'reason' => 'invalid attacker');
        }

        # get actual number of soldiers the attacker has
        $attack_with = null;
        $soldiers_available = $attacker_query['soldiers'];

        # short circuit - the attacker has insufficient soldiers
        if ($soldiers_available <= 0 or is_null($soldiers_available))
        {
            return array('response' => 'fail', 'reason' => 'insufficient soldiers');
        }

        # save defenders into local alias
        $defenders = $venue_query['defenders'];

        # short circuit - not defended, default win for attacker
        if($defenders <= 0 or is_null($defenders))
        {
            /*
             * set defenders to 0
             * set mayor to attacker
             * kick ex-mayor to the curb
             *
             * if undefended the
             *
             */
            $venues_db->update(array('id' => $id),
                               array('$set' => array('soldiers' => 0,
                                                     'mayor' => (string) $username,
                                                     'defenders' => (int) $leavebehind),
                                                     'last_attacked_on' => $the_date,
                                                     'last_attacked_by' => $username),
                               array('$pull' => array('players' => $current_mayor)));

            return array('result' => 'ok', 'outcome' => 'win');
        }

        #proceed with attack

        #prepare attacker-----------------------------------------------------

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

        # prepare dice cup
        $attack_value = 0;

        # roll dice per soldier and push onto $attack_stack
        for ($rolls = 0 ; $rolls < $attack_with ; $rolls++)
        {
            $attack_value += rand(1,6);
        }


        #prepare defender-----------------------------------------------------

        # assign dice
        $defend_value = 0;

        # roll dice per soldier and push onto $defend_stack
        for ($rolls = 0 ; $rolls < $defenders ; $rolls++)
        {
            $defend_value += rand(1,6);
        }

        # roll additional 12 sided die as per Game Design Document
        $defend_value += rand(1,12);

        # decide winner / loser (tie goes to the house)

        #attacker wins
        if ($attack_value > $defend_value)
        {

        }
        #defender wins
        else
        {

        }

        $users->update(array('username' => $username),
                       array('$inc' => array('soldiers' => $soldiers_available)));

        # remove from venue (sets field to zero
        # TODO determine if soldier related timestamps need altering
        $venues_db->update(array('id' => $id),
                           array('$set' => array('soldiers' => 0,
                                                 'soldiers_removed_on' => $the_date,

                           )));





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
