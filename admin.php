<?php

# https://github.com/jakesankey/PHP-RestServer-Class/blob/master/RestServer.php
require_once('RestServer.php');


/**
 * Class WarFareSquare
 */

class WFS_Admin
{
    /**
     * @param $username
     * @param $password
     * @param $first
     * @param $last
     *
     * @return array a json string with the user details echoed back to the caller
     *
     * IMPORTANT:  please md5 encode the password
     */
    public function register($username, $password, $first, $last)
    {
        $users = null;
        include('mongo_setup_users.php');

        try
        {
            #move this to soldiers.php
            #$my_venues = array('id' => '','soldiers_placed' => 0);


            $insert_array = array('username' => (string) $username,
                'password' => (string) $password,
                'first' => (string) $first,
                'last' => (string) $last,
                'soldiers' => 1,
                'last_daily_soldier' => date('U'),
                'venues' => array(), #$my_venues,
                'lat' => '',
                'lng' => '' );

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

        #prepare return
        $insert_array['response'] = (string) $return_code;

        return $insert_array;
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
    public function login($username, $password)
    {
        $users = null;
        include('mongo_setup_users.php');

        # calculate the seconds in a day for soldier calculations
        $seconds_per_day = 24 * 60 * 60;

        # possibly add as field in user document
        $soldiers_per_day = 1;

        # query database for the user
        $login_query = $users->findOne(array("username" => (string) $username,
                                             "password" => (string) $password));

        # return 'fail' response if either username or password or both are incorrect or not found
        if(is_null($login_query))
        {
            return array('response' => 'fail');
        }
        # log the user in and see if they deserve a soldier

        # give the user his soldier for the day
        # check to see if it has already not been given

        $time_since_last_soldier = $login_query['last_daily_soldier'] - date('U');

        if ($time_since_last_soldier > $seconds_per_day)
        {
            try
            {
                # issue new soldier to user and update the last_daily_soldier field
                $users->update(array('username'=> (string) $username),
                               array('$inc' => array('soldiers' => $soldiers_per_day)),
                               array('$set' => array('last_daily_soldier' => date('U'))));
            }
            catch(MongoCursorException $e)
            {
                return array('response' => 'ok', 'warning' => 'solider_fail');
            }
        }

        #verify user stats and return to caller

        #perform query
        $verify_user = $users->findOne(array('username'=> (string) $username));

        if (!is_null($verify_user))
        {
            return array('response' => 'ok');
        }
        {
            return array('response' => 'fail', 'reason' => 'user not found');
        }
    }
}

###################### MAIN

$rest = new RestServer();
$rest->addServiceClass('WFS_Admin');
$rest->handle();
