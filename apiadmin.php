<?php

# https://github.com/jakesankey/PHP-RestServer-Class/blob/master/RestServer.php
require_once('RestServer.php');


/**
 * Class WFS_Admin
 *
 * contains methods register, login and logout
 *
 */

class WFS_Admin
{
    /**
     * @param $username
     * @param $password
     * @param $first
     * @param $last
     * @param $lat        for spawn 
     * @param $lng        for spawn location
     *
     * @return array a json string with the user details echoed back to the caller
     *
     * IMPORTANT:  please md5 encode the password
     */
    public function register($username, $password, $first, $last, $lat, $lng)
    {
        $users = null;
        require_once('mongo_setup_users.php');

        try
        {
            #move this to apisoldiers.php
            #$my_venues = array('id' => '','soldiers_placed' => 0);


            $insert_array = array('username' => (string) $username,
                'password' => (string) $password,
                'first' => (string) $first,
                'last' => (string) $last,
                'soldiers' => 1,
                'last_daily_soldier' => date('U'),
                'coupons' => array(),

                #home base / spawn site
                'lat' => (string) $lat,
                'lng' => (string) $lng  );

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

        #verify user stats and return to caller
        #perform query
        $verify_user = $users->findOne(array('username'=> (string) $username),
                                       array('_id' => 0, 'password' => 0));

        #prepare return
        $verify_user['response'] = (string) $return_code;

        if (!is_null($verify_user))
        {
            $verify_user['response'] = 'ok';
            return $verify_user;
        }
        {
            return array('response' => 'fail', 'reason' => 'user not found');
        }
    }

    /**
     * @param $username
     * @param $password
     * @return array
     *
     * Logs in an existing warfoursquare user to the server
     * adds username to the 'online' collection
     *
     *
     * IMPORTANT:   remember to md5 encode password
     */
    public function login($username, $password)
    {
        $online = $users = null;
        require_once('mongo_setup_users.php');

        # calculate the seconds in a day for soldier calculations
        $seconds_per_day = 24 * 60 * 60;

        # possibly add as field in user document
        $soldiers_per_day = 1;

        # query database for the user
        $login_query = $users->findOne(array("username" => (string) $username,
                                             "password" => (string) $password));

        $the_date = date('U');

        # return 'fail' response if either username or password or both are incorrect or not found
        if(is_null($login_query))
        {
            return array('response' => 'fail',  'reason' => 'bad user');
        }
        # log the user in and see if they deserve a soldier

        # give the user his soldier for the day
        # check to see if it has already not been given

        $time_since_last_soldier = $the_date - $login_query['last_daily_soldier'];

        if ($time_since_last_soldier > $seconds_per_day)
        {
            try
            {
                # issue new soldier to user and update the last_daily_soldier field
                $users->update(array('username'=> (string) $username),
                               array('$inc' => array('soldiers' => $soldiers_per_day)),
                               array('$set' => array('last_daily_soldier' => $the_date)));
            }
            catch(MongoCursorException $e)
            {
                return array('response' => 'ok', 'warning' => 'solider_fail');
            }
        }

        #verify user stats and return to caller
        #perform query
        $verify_user = $users->findOne(array('username'=> (string) $username),
                                       array('_id' => 0, 'password' => 0));

        if (!is_null($verify_user))
        {
            $verify_user['response'] = 'ok';
            return $verify_user;
        }
        {
            return array('response' => 'fail', 'reason' => 'user not found');
        }
    }


    /**
     * @param $username   string
     * @param $secret     string    admin can log a user out without their pwd
     * @return array
     *
     *
     * what must be done on logout?
     *
     * - user can stay mayor of venues
     * - user must be checked out of venues to which they are not mayor
     *
     *
     * Logs out an existing warfoursquare user from the server
     * removes username from the 'online' collection
     *
     */
    public function logout($username, $secret)
    {
        require_once('../../../wfs_secret.php');

        if (strcmp(WFS_SECRET, $secret) != 0)
        {
            return array('response' => 'fail', 'reason' => 'invalid wfs secret');
        }

        # setup & initialize foursquare api and mongodb connections
        $foursquare = $users = $venues_db = null;
        require_once('mongo_setup_venues_and_users.php');

        # query database for the user
        $logout_query = $users->findOne(array("username" => (string) $username));

        # return 'fail' response if either username is incorrect or not found
        if(is_null($logout_query))
        {
            return array('response' => 'fail', 'reason' => 'bad user');
        }

        # check all venues to see if the user is checkedin but is not the mayor
        # if they are the mayor, leave them, if not remove them
        $not_the_mayor_here = $venues_db->find(array("username" => array('$ne' => (string) $username)));

        foreach($not_the_mayor_here as $get_out_of_dodge)
        {
            $id = $get_out_of_dodge['id'];
            $venues_db->update(array('username'=> (string) $username),
                               array('$pull' => array('players' => (string) $username)));
        }

    }

}

###################### MAIN

$rest = new RestServer();
$rest->addServiceClass('WFS_Admin');
$rest->handle();
