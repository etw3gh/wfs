<?php

# https://github.com/jakesankey/PHP-RestServer-Class/blob/master/RestServer.php
require_once('RestServer.php');


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
     * @TODO determine appropriate setting for soldier_added_on (currently set to null)
     *
     */
    public function attack($id, $username, $attackers, $leavebehind)
    {
        $venues_db = $users = null;
        require_once('mongo_setup_venues_and_users.php');

        # convert $leavebehind to a number
        $leavebehind = (int) $leavebehind;

        # get venue details
        $venue_query = $venues_db->findOne(array('id' => $id));

        # short circuit - bad venue
        if(is_null($venue_query))
        {
            return array('response' => 'fail', 'reason' => 'invalid venue');
        }

        # save the date
        $the_date = date('U');

        # set up short circuit test for re-attack too soon by same user

        $last_attacked_by  = $venue_query['last_attacked_by'];

        #if attacking user is the same as the last attacker calculate if its been 12 hours
        if (!is_null($last_attacked_by) and strcmp($username, $last_attacked_by) != 0)
        {
            $last_attacked_on = $venue_query['last_attacked_on'];
            $seconds_in_12_hours = 12 * 60 * 60;
            $seconds_since_last_attack = $the_date - $last_attacked_on;

            $next_attack_may_occur_in = $seconds_in_12_hours - $seconds_since_last_attack;

            # short circuit - re-attack too within 12 hours
            if ($next_attack_may_occur_in > 0)
            {
                return array('response' => 'fail',
                             'reason' => 'too soon',
                             'remain' => $next_attack_may_occur_in);
            }
        }

        # save current mayor into local alias
        $current_mayor = $venue_query['mayor'];

        /* 
           INEFFICENT METHOD ADDED AS CONVENIENCE
           PROPER ORDER IS TO CHECKOUT/CHECKIN/ATTACK

           short circuit - no mayor
           attacker is the new mayor
           $leavebehind number of soldiers is placed at venue
           soldiers reduced
           strays deleted (should not be present in the first place)
        */
        if(strcmp($current_mayor, '') == 0 or is_null($current_mayor))
        {
            # user must be checked out of any other venues
            # user may only be checked into one venue so there is at most one to find
            $all_venues = $venues_db->findOne(array('mayor' => $username));
            if (!is_null($all_venues))
            {
               $venue_to_checkout = $all_venues['id'];
               $remove_defenders = $all_venues['defenders'];
               $venues_db->update(array('id' => $venue_to_checkout),
                                  array('$set' => array('mayor' => null,
                                                        'defenders' => 0)),
                                  array('$pull' => array('players' => $username))); 
            }

            $venues_db->update(array('id' => $id),
                               array('$set' => array('mayor' => (string) $username,
                                                     'defenders' => $leavebehind,
                                                     'last_attacked_on' => $the_date,
                                                     'soldiers' => 0,
                                                     'last_attacked_by' => $username)),
                               array('$push' => array('players' => (string) $username)));
           
            #adjust reduction by number of defenders the player will keep by checking out
            $reduce_by = (-1) * ($leavebehind - $remove_defenders);    
 
            $users->update(array('username' => $username),
                           array('$inc' => array('soldiers' => $reduce_by)));

            return array('response' => 'ok', 'notice' => 'user checked in & is mayor');
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

        # save defenders & venue soldiers into local aliases
        $defenders = $venue_query['defenders'];
        $venue_soldiers = $venue_query['soldiers'];

        # short circuit - not defended, default win for attacker
        if (is_null($defenders))
        {
            $defenders = 0;
        }

        if($defenders <= 0)
        {
            /*
             * set venue soldiers to 0
             * assign venue soldiers to attacker
             * set defenders to $leavebehind
             * adjust last_attacked_on and last_attacked_by
             * set mayor to attacker
             * kick ex-mayor to the curb
             *
             * set soldier_removed_on to current date
             * ??? set soldier_added_on to null ????
             *
             * if undefended no soldier is lost by default winner
             *
             */
            $venues_db->update(array('id' => $id),
                               array('$set' => array('soldiers' => 0,
                                                     'mayor' => (string) $username,
                                                     'defenders' => (int) $leavebehind),
                                                     'last_attacked_on' => (string) $the_date,
                                                     'last_attacked_by' => (string) $username,
                                                     'soldier_added_on' => null,
                                                     'soldier_removed_on' => (string) $the_date),
                               array('$pull' => array('players' => $current_mayor)));

            # subtract $leavebehind number of soldiers from the user
            # mongodb has no $dec operator so $inc by negative
            # adjust by any stray venue soldiers there may be hanging about
            $reduce_soldiers_by = (-1) * ($leavebehind - $venue_soldiers);

            $users->update(array('username' => $username),
                           array('$inc' => array('soldiers' => (int) $reduce_soldiers_by)));

            return array('result' => 'ok', 'outcome' => 'win',
                         'attack' => 'default', 'defend' => 'default');
        }

        ########################################################################################
        ########################################################################################

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

        # roll dice per soldier and accumulate the value
        for ($rolls = 0 ; $rolls < $attack_with ; $rolls++)
        {
            $attack_value += rand(1,6);
        }


        #prepare defender-----------------------------------------------------

        # assign dice
        $defend_value = 0;

        # roll dice per soldier and accumulate the value
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
            /*
             *  pickup any venue soldiers for the attacker
             *  set venue soldiers to 0
             *
             *  set defenders to $leavebehind
             *  adjust last_attacked_on and last_attacked_by
             *  set mayor to attacker
             *  kick ex-mayor to the curb
             *
             *  reduce attacker soldiers by 1
             *
             * set soldier_removed_on to current date
             * set soldier_added_on to null
             *
             *  eliminate defenders by
             *  replacing defenders with $leavebehind which are owned by attacker / mayor /winner
             *
             */

            $venues_db->update(array('id' => $id),
                               array('$set' => array('soldiers' => 0,
                                                     'mayor' => (string) $username,
                                                     'defenders' => (int) $leavebehind),
                                                     'last_attacked_on' => (string) $the_date,
                                                     'last_attacked_by' => (string) $username,
                                                     'soldier_added_on' => null,
                                                     'soldier_removed_on' => (string) $the_date),
                               array('$pull' => array('players' => $current_mayor)));

            # subtract $leavebehind number of soldiers from the user

            # mongodb has no $dec operator so $inc by negative
            # adjust for any stray venue soldiers
            $reduce_soldiers_by = (-1) * ($leavebehind - $venue_soldiers);

            $users->update(array('username' => $username),
                           array('$inc' => array('soldiers' => (int) $reduce_soldiers_by)));

            return array('result' => 'ok', 'outcome' => 'win',
                         'attack' => $attack_value, 'defend' => $defend_value);
        }
        #defender wins
        else
        {
            # eliminate attacker defenders
            # mongodb has no $dec operator so $inc by negative

            $eliminate_attackers = (-1) * $leavebehind;

            $users->update(array('username' => $username),
                           array('$inc' => array('soldiers' => (int) $eliminate_attackers)));

            /*
             * reduce defender soldiers by 1
             * adjust last_attacked_on and last_attacked_by
             * kick attacker to the curb
             */
            $venues_db->update(array('id' => $id),
                               array('$set' => array('last_attacked_on' => (string) $the_date,
                                                     'last_attacked_by' => (string) $username)),
                               array('$inc' => array('defenders' => -1 )),
                               array('$pull' => array('players' => $username)));

            return array('result' => 'ok', 'outcome' => 'loss',
                         'attack' => $attack_value, 'defend' => $defend_value);
        }
    }

}

###################### MAIN

$rest = new RestServer();
$rest->addServiceClass('WFS_Attack');
$rest->handle();
