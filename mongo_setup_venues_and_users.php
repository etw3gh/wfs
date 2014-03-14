<?php
//database setup
$mongo = new MongoClient();
$wfs = $mongo->selectDB('wfs');
$users = $wfs->selectCollection('users');
$users->ensureIndex(array("username" => 1), array("unique" => 1));
$online = $wfs->selectCollection('online');
$venues_db = $wfs->selectCollection('venues');
//unique id allows for easy findOne / is_null check
$venues_db->ensureIndex(array('id' => 1), array('unique' => 1));