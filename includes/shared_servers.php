<?php
//Calls to plex.tv web API call for shared servers and
//returns an array of values needed for querying their data

//Are these 3 lines needed? 
date_default_timezone_set(@date_default_timezone_get());

require_once(dirname(__FILE__) . '/../config/config.php');
require_once(dirname(__FILE__) . '/timeago.php');

include (dirname(__FILE__) . '/recently_added.php');

// Call to Plex.tv for shared server information
$plexServer = array(); //Using this array to carry plex.tv request
if (!empty($plexWatch['myPlexAuthToken'])) {
	$plexServer['plexAuthToken'] = $plexWatch['myPlexAuthToken'];
} else {
	$plexServer['plexAuthToken'] = '';
}
$plexServer['sharedServerAddress'] = 'plex.tv';
$plexServer['sharedServerPort'] = '443'; // Should always be SSL
$serverCall = getPmsData('/pms/servers', $plexServer) or
	die ("<div class='alert alert-warning'>Failed to access Plex Media Server. " .
		"Please check your settings.</div>");
$serverXml = simplexml_load_string($serverCall);

// Run through each feed object to create 2-dim array
$serverArray = array();
$i = 0;
foreach ($serverXml->Server as $server) {
	$serverArray[$i] = array
	(
	'sharedServerName' => (string)$server['sourceTitle'],
	'plexAuthToken' => (string)$server['accessToken'],
	'sharedServerAddress' => (string)$server['address'],
	'sharedServerPort' => (string)$server['port']
	);
	$i++;
}

// For each returned server (besides ourself which will have a blank sharedServerName call recently_added.php
// TODO: Separate each server call to take advantage of async calls.
foreach ($serverArray as $v) {
	if (!empty($v['sharedServerName'])) {
		echo "<h4>" . $v['sharedServerName'] . "'s Recently Added </h4>";
		recentlyAdded($v);
	}
}
?>
