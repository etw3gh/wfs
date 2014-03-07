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

class WFS_Nearby
{
    /**
     * method to return $how_many venues with the highest number of foursquare checkins
     *
     * grabs json from foursquare and performs aggregations on the results in mongodb
     * presents nicely sorted (by foursquare checkins) json array to the calling application
     * and only includes a few important details of the original response
     *
     * creates a temporary aggregation table with temp_id = date('U')
     * table is erased immediately after aggregation is performed
     *
     * @param $lat
     * @param $lng
     * @param $username
     * @param $how_many
     * @param $restrict_categories string (true or false)
     *
     * @return array
     *
     * @TODO radius currently 2000M - allow app to modify
     */
    public function nearby($lat, $lng, $username, $how_many, $restrict_categories)
    {
        $foursquare = $venues_db = $nearby_venues = $wfs = null;
        include('mongo_setup_venues.php');include('foursquare_setup.php');
        $nearby_venues = $wfs->selectCollection('nearby');

        //TODO: determine if radius is sufficient and/or increase upon low results
        $radius = 2000;

        //add or omit category restrictions
        if (strtolower($restrict_categories) == 'true')
        {
            //prepare foursquare categories
            $food_4s_id = '4d4b7105d754a06374d81259';
            $arts_4s_id = '4d4b7104d754a06370d81259';
            $bar_4s_id  = '4d4b7105d754a06376d81259';
            $shopping_4s_id = '4d4b7105d754a06378d81259';
            $travel_4s_id = '4d4b7105d754a06379d81259';
            $categories = $food_4s_id . ',' . $arts_4s_id . ',' . $bar_4s_id . ',' . $shopping_4s_id . ',' . $travel_4s_id;

            //prepare default params with categories selected
            $params = array('ll' => "$lat, $lng",
                'radius' => $radius,
                'categories' => $categories);
        }
        else
        {
            //prepare default params without categories selected
            $params = array('ll' => "$lat, $lng", 'radius' => $radius);
        }

        //Perform a request to a public resource
        $response = $foursquare->GetPublic("venues/search",$params);
        $venues = json_decode($response);

        //unique timestamp to label temporary document or 'table' so it can be erased
        $temp_id = date('U');
        //prep mongo db document
        $nearby_venues->insert(array('temp_id' => $temp_id, 'venues' => array() ));


        //push relevant venue details to doc
        foreach ($venues->response->venues as $v)
        {
            $insert_array =array();
            $insert_array['id'] = $v->id;
            $insert_array['name'] = $v->name;
            $insert_array['distance'] = $v->location->distance;
            $insert_array['checkins'] = $v->stats->checkinsCount;

            try
            {
                $nearby_venues->update(array('temp_id' => $temp_id),
                    array('$push' => array('venues' => $insert_array )));
            }
            catch (MongoCursorException $e)
            {
                return array('response' => 'fail', 'reason' => $e->getMessage());
            }
            catch (MongoException $e)
            {
                return array('response' => 'fail', 'reason' => 'other database error');
            }
        }

        try
        {
            /*
                AGGREGATION PIPELINE
                $unwind: used to access nested array 'venues'
                $match: restrict to the current temp_id just in case more exist
                $sort: -1 used for descending order sort of venues[checkins]
                $limit: returns only however many the function is asked for ($how_many)
                $project: create projection of 'columns' to exclude (0) mongodb _id
                          and include (1) the venues array
            */
            $agg_array = array( array('$unwind' => '$venues'),
                array('$match' => array('temp_id' => $temp_id)),
                array('$sort' => array('venues.checkins' =>-1 )),
                array('$limit' => (int) $how_many),
                array('$project' =>
                    array('_id' => 0,
                        'venues' => 1)));
            //perform the aggregation
            $aggregate = $nearby_venues->aggregate( $agg_array );

            //attempt to delete the temporary document (no big deal if it fails)
            try
            {
                $nearby_venues->remove(array('temp_id' => $temp_id));
            }
            catch (MongoCursorException $e){
                //nop
            }

            //return the aggregation as the value for key 'top_venues'
            return array('response' => 'ok', 'top_venues' => $aggregate);
        }
        catch(MongoCursorException $e)
        {
            return array('response' => 'fail', 'reason' => 'aggregation');
        }
    }

}

######################MAIN

$rest = new RestServer();
$rest->addServiceClass('WFS_Nearby');
$rest->handle();
