<?php

require_once("FoursquareAPI.class.php");
require_once('../../../secret.php');

$foursquare = new FoursquareAPI(CLIENT_ID, CLIENT_SECRET);