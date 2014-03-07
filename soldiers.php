<?php

# https://github.com/jakesankey/PHP-RestServer-Class/blob/master/RestServer.php
require_once('RestServer.php');

/**
 * Class WarFareSquare
 *
 * A class that holds api methods which serves to decouple the application logic
 * from the application implementation(s) and platform(s).
 *
 * Serves json strings back to whomever calls the api methods
 */

class WFS_Soldiers
{
    /**
     * method to allow the venue's controlling user to pickup the soldier.
     *
     * @param $id       string    unique foursquare venue id
     * @param $username string    unique warfoursquare username
     *
     * @return array
     *
     * @TODO try catch around mongodb operations
     *
     */
    public function pickup($id, $username)
    {
        $venues_db = null; $users_db = null;
        include('mongo_setup_venues_and_users.php');

        //determine if our user is the mayor of location supplied by $id
        $is_mayor_query = $venues_db->findOne(array('mayor' => $username, 'id' => $id));

        if(!is_null($is_mayor_query))
        {
            $soldiers_available = $is_mayor_query['daily_soldiers'];
            if ($soldiers_available > 0)
            {
                //first add to user
                $users_db->update(array('username' => $username),
                    array('$inc' => array('soldiers' => $soldiers_available)));

                //remove from venue (sets field to zero
                //TODO determine if soldier related timestamps need altering
                $venues_db->update(array('id' => $id),
                    array('$set' => array('daily_soldiers' => 0,
                        'daily_soldiers_removed_on' => date('U'))));
            }
            else
            {
                return array('response' => 'fail', 'reason' => 'no soldiers');
            }
        }
        else
        {
            return array('response' => 'fail', 'reason' => 'user not mayor');
        }

        //TODO return stats . . .
        return array('response' => 'ok');
    }

    /**
     * Method that allows a venue's controlling user to place defending soldiers
     *
     * @param $id       string    unique foursquare venue id
     * @param $username string    unique warfoursquare username
     * @param $number   string of an integer representing the number of soldiers to place
     * @return array
     *
     * @TODO try / catch around mongodb operations
     */
    public function place($id, $username, $number)
    {
        $venues_db = null; $users_db = null;
        include('mongo_setup_venues_and_users.php');

        //determine if our user is the mayor of location supplied by $id
        $is_mayor_query = $venues_db->findOne(array('mayor' => $username, 'id' => $id));

        if(is_null($is_mayor_query))
        {
            return array('response' => 'fail', 'reason' => 'user not mayor');
        }

        //prepare return array
        $success_array = array('response' => 'ok');

        //according to gdd maximum 10 dice / soldiers per venue
        $max_placement = 10;

        $user_query = $users_db->findOne(array('username' => $username));

        if (is_null($user_query))
        {
            return array('response' => 'fail', 'reason' => 'invalid user');
        }

        //now we know the user is the mayor and that the user exists

        //retrieve the number of soldiers already defending the location
        //by definition, they will belong to this user and count towards the max
        $soldiers_already_defending = $is_mayor_query['soldiers'];
        $user_able_to_place = $max_placement - $soldiers_already_defending;

        //retrieve the actual number of soldiers the user has available to them
        $actual_number_of_soldiers = $user_query['soldiers'];

        if($number > $actual_number_of_soldiers)
        {
            $success_array['warning'] = "$number requested, $actual_number_of_soldiers placed";
            $number = $actual_number_of_soldiers;

            //account for case where user wishes to deploy more than the max number allowed
            if ($number > $user_able_to_place)
            {
                $success_array['warning'] = "$number requested, $user_able_to_place placed";
                $number = $user_able_to_place;
            }
        }

        //now place $number of soldiers at the location and remove -$number from the user
        //mongo has no '$dec' operator...
        $reduce_by = $number * (-1);
        $users_db->update(array('username' => $username),
            array('$inc' => array('soldiers' => $reduce_by)));

        $venues_db->update(array('mayor' =>$username),
            array('$inc' => array('soldiers' => $number)));


        $success_array['stats'] = array('usersoldiers' => $actual_number_of_soldiers - $number,
            'venuesolders' => $soldiers_already_defending + $number);
        return $success_array;
    }
}

###################### MAIN

$rest = new RestServer();
$rest->addServiceClass('WFS_Soldiers');
$rest->handle();
