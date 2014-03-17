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
     */
    public function attack($id, $username, $attackers)
    {
        $venues_db = null; $users = null;
        include('mongo_setup_venues_and_users.php');

        # determine if our user is the mayor of location supplied by $id
        $is_mayor_query = $venues_db->findOne(array('mayor' => $username, 'id' => $id));

        if(is_null($is_mayor_query))
        {
            return array('response' => 'fail', 'reason' => 'no mayor to attack');
        }



        # TODO return stats . . .
        return array('response' => 'ok');
    }

}

###################### MAIN

$rest = new RestServer();
$rest->addServiceClass('WFS_Attack');
$rest->handle();
