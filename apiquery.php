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

        $user_query = $users->findOne(array('username' => (string) $username),
                                      array('_id' => 0));

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

        $venue_query = $venues_db->findOne(array('id' => (string) $id),
                                           array('_id' => 0));

        if (!is_null($venue_query))
        {
            return array('result' => 'ok', 'venue' => $venue_query);
        }

        return array('result' => 'fail', 'reason' => 'bad venue');
    }


    /**
     * method to return a list venue documents corresponding to a query of the venue name
     *
     * @param $name
     * @param $secret
     * @return array
     *
     */
    public function venuename($secret, $name)
    {
        # strip spaces
        $name = trim($name);

        if (strlen($name) < 2)
        {
            return array('response' => 'fail', 'reason' => 'invalid name');
        }

        require_once('../../../wfs_secret.php');
        if (strcmp(WFS_SECRET, $secret) != 0)
        {
            return array('response' => 'fail', 'reason' => 'invalid wfs secret');
        }

        $venues_db = null;
        include('mongo_setup_venues.php');

        # query will find case insensitive occurences of $name in all venue names
        $name_regex = new MongoRegex("/$name/i");
        $venue_query = $venues_db->find(array('name' => $name_regex),
                                        array('_id' => 0));

        if (!is_null($venue_query))
        {
            return array('result' => 'ok', 'venue' => iterator_to_array($venue_query));
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
            return array('result' => 'ok', 'mayors' => iterator_to_array($mayors_query));
        }

        return array('result' => 'fail', 'reason' => 'contact DB admin ASAP');
    }


    /**
     * method that returns $how_many venues ranked by most weakly defended
     *
     * @param $secret
     * @param $howmany -1 will return the strongest mayor. any other negative will
     *                    return all the mayors sorted in descending order (strongest at the top)
     *
     * @return array
     *
     */
    public function weakest($secret, $howmany)
    {
        $venues_db = null;
        include('mongo_setup_venues.php');
        require_once('../../../wfs_secret.php');
        if (strcmp(WFS_SECRET, $secret) != 0)
        {
            return array('response' => 'fail', 'reason' => 'invalid wfs secret');
        }

        # short circuit
        if ($howmany == 0)
        {
            return array('response' => 'fail', 'reason' => 'howmany must be greater or less than zero');
        }

        # set sort order
        if ($howmany > 0)
        {
            $sort_order = 1;
            $order_by = 'weakest';
        }
        elseif ($howmany == -1)
        {
            $sort_order = -1;
            $howmany = 1;
            $order_by = 'strongest';
        }
        else
        {
            $sort_order = -1;
            $howmany = $venues_db->count();
            $order_by = 'strongest';
        }

        # perform this query in php
        # db.venues.find({},{_id:0, name:1, id:1, defenders:1}).sort({'defenders' :1}).limit(1)
        $query = $venues_db->find(array(), #empty array means find all
                                  array('_id' => 0,
                                        'id' => 1,
                                        'defenders' => 1,
                                        'name' => 1))->sort(array('defenders' => $sort_order))->limit($howmany);

        if (!is_null($query))
        {
            return array('result' => 'ok', $order_by =>  iterator_to_array($query));
        }

        return array('result' => 'fail', 'reason' => 'contact DB admin ASAP');
    }
}

###################### MAIN

$rest = new RestServer();
$rest->addServiceClass('WFS_Query');
$rest->handle();