<?php
require_once('RestServer.php');

/**
 * Class WarFareSquare
 *
 * A class that holds api methods which serves to decouple the application logic
 * from the application implementation(s) and platform(s).
 *
 * Serves json strings back to whomever calls the api methods
 */

class WFS_Admin
{
    /**
     * @param $username
     * @param $password
     * @param $first
     * @param $last
     *
     * @param string $full_response
     * @return array|null
     *
     * IMPORTANT:  please md5 encode the password
     */
    public function register($username, $password, $first, $last, $full_response)
    {
        $users = null; include('mongo_setup_users.php');

        $insert_array = null;

        try
        {
            $my_venues = array('id' => '',
                'soldiers_placed' => 0);


            $insert_array = array('username' => (string) $username,
                'password' => (string) $password,
                'first' => (string) $first,
                'last' => (string) $last,
                'soldiers' => 1,
                'last_daily_soldier' => date('U'),
                'venues' => $my_venues);

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

        if(strtolower($full_response) == 'true' )
        {
            $insert_array['response'] = (string) $return_code;
            return $insert_array;
        }
        else
        {
            return array('response' => (string) $return_code);
        }
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
        $users = null; include('mongo_setup_users.php');

        //calculate the seconds in a day for soldier calculations
        $seconds_per_day = 24 * 60 * 60;

        //possibly add as field in user document
        $soldiers_per_day = 1;

        //query database for the user
        $login_query = $users->findOne(array("username" => (string) $username,
            "password" => (string) $password));

        //return 'fail' response if either username or password or both are incorrect or not found
        if(is_null($login_query))
        {
            return array('response' => 'fail');
        }
        //log the user in and see if they deserve a soldier
        else
        {
            //give the user his soldier for the day
            //check to see if it has already not been given

            $time_since_last_soldier = $login_query['last_daily_soldier'] - date('U');

            if ($time_since_last_soldier > $seconds_per_day)
            {
                try
                {
                    //issue new soldier to user and update the last_daily_soldier field
                    $users->update(array('username'=> (string) $username),
                        array('$inc' => array('soldiers' => $soldiers_per_day)),
                        array('$set' => array('last_daily_soldier' => date('U'))));
                }
                catch(MongoCursorException $e)
                {
                    return array('response' => 'ok', 'daily_soldier' => 'fail');
                }
            }
            return array('response' => 'ok');
        }
    }
}


$rest = new RestServer();
$rest->addServiceClass('WFS_Admin');
$rest->handle();
