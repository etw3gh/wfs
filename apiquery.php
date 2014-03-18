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

        if (strcmp(WFS_SECRET, $secret))
        {
            return array('response' => 'fail', 'reason' => 'invalid wfs secret');
        }


        return true;
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
        if (strcmp(WFS_SECRET, $secret))
        {
            return array('response' => 'fail', 'reason' => 'invalid wfs secret');
        }

        return true;
    }

    /**
     * method that returns a list of all venues id's with username of the mayor
     * also returns number of soldiers the mayor is defending the venue with
     *
     * @param $secret
     * @return array
     */
    public function mayors($secret)
    {
        require_once('../../../wfs_secret.php');
        if (strcmp(WFS_SECRET, $secret))
        {
            return array('response' => 'fail', 'reason' => 'invalid wfs secret');
        }

        return true;
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
        if (strcmp(WFS_SECRET, $secret))
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