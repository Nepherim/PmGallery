<?php if (!defined('PmWiki')) exit();
/*  Copyright 2008 David Gilbert ( http://solidgone.org/lightweight-php-picasa-api )
    You can redistribute this file and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
 *
 * Helper file for feed reading functions.
*/

/**
* Deletes files in $dir, matching the wildcard $wild, which are older than $age
*/
function cleanDir($dir, $wild, $age) {
	if (!is_dir($dir)) return;
	$expire = time()-$age;
	foreach (glob($dir. '/'. $wild) as $filename) {
		if( filemtime($filename) < $expire ) {
			debugLog ( (unlink($filename) ? 'deleted' : 'FAILED to delete'). ' cached file '.$filename);
		}
	}
}

function readFeed ($location, $cacheLife, $cacheDir, $proxy) {
	$cachePrefix = 'pmgallery_';
	$cacheDir = (empty($cacheDir) ? dirname(__FILE__). '/cache' : $cacheDir);
	$cache = $cacheDir. '/'. $cachePrefix. md5($location);

	// clean out old cache files
	cleanDir($cacheDir, $cachePrefix.'*', $cacheLife);

	//First check for an existing version of the time, and then check to see whether or not it's expired.
	if(!empty($cacheLife) && file_exists($cache) && filemtime($cache) > (time() - $cacheLife)) {
		debugLog('cached...');
		//If there's a valid cache file, load its data.
		$feedXml = file_get_contents($cache);
	} else {
		debugLog('NOT cached...');
		//If there's no valid cache file, grab a live version of the data and save it to a temporary file.
		//Once the file is complete, copy it to a permanent file.  (This prevents concurrency issues.)
		//TODO: Error handling -- unable to open stream

		$ctx = stream_context_create(array(
			 'http' => array(
		//        'timeout' => 1,
				  'proxy' => $proxy, // This needs to be the server and the port of the NTLM Authentication Proxy Server.
				  'request_fulluri' => true,
				  )
			 )
		);
		$feedXml = file_get_contents($location, false, $ctx);
		$tempName = tempnam($cacheDir,'t_'.$cachePrefix);	// prefix with t_ to prevent other processes deleting
		file_put_contents($tempName, $feedXml);
		if (copy($tempName, $cache)) {	// copy forces overwrite if file is past cachelife
			unlink($tempName);
		}
	}
	return $feedXml;
}
