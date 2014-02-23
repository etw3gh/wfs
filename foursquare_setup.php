<?php
//Foursquare setup
require_once('../../../secret.php');

$foursquare = new FoursquareAPI(CLIENT_ID, CLIENT_SECRET);