<?php
    require_once("FoursquareAPI.class.php");
    require_once('RestServer.php');

    // http://wfs.openciti.ca?method=MethodName&param1=Param1Value&param2=Param2Value
    class WarFareSquare
    {
        //example of a function
        public function hello($name){return(array("response" => "Hello $name"));}

        
        #each endpoint has its own function
        
        //please md5 encode the password
        public function register_user($username, $password, $first, $last, $lat=-1, $lng=-1) 
        {
            //must be repeated for each api endpoint function due to RestServer functionality
            $mongo = new MongoClient();
            $wfs = $mongo->selectDB('wfs');
            $users = $wfs->selectCollection('users');
            $users->ensureIndex(array("username" => 1), array("unique" => 1));
            $foursquare = new FoursquareAPI($client_key,$client_secret);
            //-------------------------------------------------------------------------------

            $return_code = 'null';
            $insert_array = null;

            try{
                $insert_array = array('username' => $username, 
                                      'password' => $password, 
                                      'first' => $first , 
                                      'last' => $last, 
                                      'lat' => $lat,
                                      'lng' => $lng);

                $users->insert($insert_array);
                $return_code = 'ok';
            }
            catch(MongoCursorException $e) 
            {
                return array("response" => "duplicate user");
            }
            catch(MongoException $e)
            {
                return array("response" => "fail");
            }



            $insert_array["response"] = $return_code;            
            return $insert_array;            
        }
 



    } //end WarFareSquare class

$rest = new RestServer();
$rest->addServiceClass(WarFareSquare);
$rest->handle();
