<?php
//database setup
$mongo = new MongoClient();
$wfs = $mongo->selectDB('wfs');
$users = $wfs->selectCollection('users');
$online = $wfs->selectCollection('online');
$users->ensureIndex(array("username" => 1), array("unique" => 1));