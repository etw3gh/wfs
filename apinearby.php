<?php

# https://github.com/jakesankey/PHP-RestServer-Class/blob/master/RestServer.php
require_once('RestServer.php');

/**
 * Class WarFareSquare
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
     *
     * table is erased immediately after aggregation is performed
     * if $KEEP_NEARBY_QUERY is set to false
     *
     * @param $lat
     * @param $lng
     * @param $howmany
     * @param $restrict boolean  restricts foursquare categories to businesses excluding landmarks
     * @param $radius int in metres
     *
     * @return array
     *
     */
    public function nearby($lat, $lng, $howmany, $restrict, $radius)
    {
        # setup & initialize foursquare api and mongodb connections
        $foursquare = $venues_db = $nearby_venues = $wfs = null;
        include('mongo_setup_venues.php');
        include('foursquare_setup.php');

        # for demonstration purposes we will keep these queries in the database
        $KEEP_NEARBY_QUERY = true;

        # access collection for temporary documents
        # TODO leverage to save foursquare api calls

        $nearby_venues = $wfs->selectCollection('nearby');

        # add or omit category restrictions to shopping type locations
        if (strcasecmp($restrict, 'true') == 0)
        {
            # prepare foursquare categories
            $category_array = array(
                /* food     */       '4d4b7105d754a06374d81259',
                /* arts     */       '4d4b7104d754a06370d81259',
                /* bars     */       '4d4b7105d754a06376d81259',
                /* shopping */       '4d4b7105d754a06378d81259',
                /* travel   */       '4d4b7105d754a06379d81259');

            #create a csv string as per the foursquare api requirements
            $categories = implode(',', $category_array);

            #prepare default params with categories selected
            $params = array('ll' => "$lat, $lng",
                            'radius' => $radius,
                            'categories' => $categories);
        }
        else
        {
            # prepare default params without categories selected
            $params = array('ll' => "$lat, $lng", 'radius' => $radius);
        }

        # Perform a request to a public resource
        $response = $foursquare->GetPublic("venues/search",$params);
        $venues = json_decode($response);

        # unique timestamp to label temporary document or 'table' so it can be erased
        $temp_id = date('U');
        # prep mongo db document
        $nearby_venues->insert(array('temp_id' => $temp_id, 'venues' => array() ));


        # push relevant venue details to temporary document
        foreach ($venues->response->venues as $v)
        {
            $insert_array =array();
            $insert_array['id'] = $v->id;
            $insert_array['name'] = $v->name;
            $insert_array['distance'] = $v->location->distance;
            $insert_array['checkins'] = $v->stats->checkinsCount;
            $insert_array['lat'] = $v->location->lat;
            $insert_array['lng'] = $v->location->lng;

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
                                array('$limit' => (int) $howmany),
                                array('$project' =>
                                    array('_id' => 0,
                                        'venues' => 1)));
            # perform the aggregation
            $aggregate = $nearby_venues->aggregate( $agg_array );

            # attempt to delete the temporary document (no big deal if it fails)

            if (!$KEEP_NEARBY_QUERY)
            {
                try
                {
                    $nearby_venues->remove(array('temp_id' => $temp_id));
                }
                catch (MongoCursorException $e)
                {
                    # nop
                }
            }

            # return the aggregation as the value for key 'top_venues'
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
