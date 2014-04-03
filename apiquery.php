<?php

# https://github.com/jakesankey/PHP-RestServer-Class/blob/master/RestServer.php
require_once('RestServer.php');

/**
 * Class WarFareSquare
 *
 * allows the caller in possession of a SECRET_ADMIN_KEY to perform
 * queries on the users and venues database collections
 *
 */

class WFS_Query
{
    /**
     * method to return a user document corresponding to a unique wfs username
     *
     * @param $username
     * @param $secret
     * @return array|bool
     */
    public function user($secret, $username)
    {
        require_once('../../../wfs_secret.php');

        if (strcmp(WFS_SECRET, $secret) != 0)
        {
            return array('response' => 'fail', 'reason' => 'invalid wfs secret');
        }

        $users = null;
        include('mongo_setup_users.php');

        $user_query = $users->findOne(array('username' => (string) $username));

        if (!is_null($user_query))
        {
            return array('result' => 'ok', 'user' => $user_query);
        }

        return array('result' => 'fail', 'reason' => 'bad user');
    }

    /**
     * method to return a venue document corresponding to a unique venue id (same as the 4s id)
     *
     * @param $id
     * @param $secret
     * @return array|bool
     */
    public function venue($secret, $id)
    {
        require_once('../../../wfs_secret.php');
        if (strcmp(WFS_SECRET, $secret) != 0)
        {
            return array('response' => 'fail', 'reason' => 'invalid wfs secret');
        }

        $venues_db = null;
        include('mongo_setup_venues.php');

        $venue_query = $venues_db->findOne(array('id' => (string) $id));

        if (!is_null($venue_query))
        {
            return array('result' => 'ok', 'venue' => $venue_query);
        }

        return array('result' => 'fail', 'reason' => 'bad venue');
    }

    /**
     * method that returns a list of all venues id's with username of the mayor
     * also returns number of soldiers the mayor is defending the venue with and venue name
     *
     * @param $secret
     * @return array
     */
    public function mayors($secret)
    {
        require_once('../../../wfs_secret.php');
        if (strcmp(WFS_SECRET, $secret) != 0)
        {
            return array('response' => 'fail', 'reason' => 'invalid wfs secret');
        }

        $venues_db = null;
        include('mongo_setup_venues.php');

        # db.venues.find({},{_id:0, mayor:1, id:1, name:1, defenders:1})
        $mayors_query = $venues_db->find(array(), #empty array means find all
                                         array('_id' => 0,
                                               'mayor' => 1,
                                               'id' => 1,
                                               'defenders' => 1,
                                               'name' => 1));

        if (!is_null($mayors_query))
        {
            return array('result' => 'ok', 'mayors' => $mayors_query);
        }

        return array('result' => 'fail', 'reason' => 'mayor query failed, check with DB admin ASAP');
    }


    /**
     * method that returns $how_many venues ranked by most weakly defended
     *
     * @param $secret
     * @param $how_many
     *
     * @return array
     *
     */
    public function weakest($secret, $how_many)
    {
        require_once('../../../wfs_secret.php');
        if (strcmp(WFS_SECRET, $secret) != 0)
        {
            return array('response' => 'fail', 'reason' => 'invalid wfs secret');
        }

        return true;
    }
}

###################### MAIN

$rest = new RestServer();
$rest->addServiceClass('WFS_Query');
$rest->handle();