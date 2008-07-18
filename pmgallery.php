<?php if (!defined('PmWiki')) exit();
/*  Copyright 2008 David Gilbert ( http://solidgone.org/pmGallery )
    You can redistribute this file and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

	Shows the clickable album covers for all public albums of user XXXX
    	(:jsgallery user=XXXX :)

    Shows photos from album 1234
    	(:jsgallery album=1234 user=XXXX :)

    Available parameters with default values
		'album'			=> '',				// if album is supplied, then show images in the album, so if album is blank then show a list of albums
		'wikitarget'	=> '', 				// for albums specify the wiki group; for pages specify the group.page
		'startimg'		=> '',				// used if paging across many images, in conjunction with 'maxresults'
		'tag'				=> '',				// used to search for a tag
		'random'			=> '',				// numer of random images to include
		'width'			=> '',				// override for image display; usually set in CSS
		'height'			=> '',				// override for image display; usually set in CSS
		'imageurl'		=> '',				// Usually set automatically, and does not need to be set by user

		// These parameters may also be set globally in config.php
		'user'			=> '',
		'thumbsize'		=> '', 				// default is 72: 32, 48, 64, 72, 144, 160,200, 288, 320, 400, 512, 576, 640, 720, 800
		'imagesize'		=> '', 				// defult is 640: 32, 48, 64, 72, 144, 160,200, 288, 320, 400, 512, 576, 640, 720, 800
		'maxresults'   => '', 				// default is 50: numeric (max # images/albums)
		'wrapper'		=> 'div',			// default is 'div': '>' separated format for outter and inner html tags: 'ul > li', 'div > div' (or 'div'), etc
		'mode'			=> '',				// 'cover': shows the cover of the single album specified; 
													// 'linkdirect': links direct to source image (used with external pugins)
		'provider'		=> 'picasa',		// where the images are coming from (picasa)

		// These parameters are usually set globally in config.php
		'urlbase'		=> 'com',			// default is 'com': source of the rss feed
		'proxy'			=> '',				// specify the proxy and port ('proxy.server.com:8080')
		'authkey'		=> '',				//
		'debug'			=> false,			//
		'cachedir' 		=> '',				// location of the cache directory. Default is [directory of this file].'/cache'. NO trailing /
		'cachelife' 	=> '7200'			// default is '7200' (2 hours): set to '0' to disable the cache

    To use this recipe, simply copy it into the cookbook/ directory, and
    add the following line to a local customization:

        include_once("$FarmD/cookbook/pmGallery.php");
*/
$RecipeInfo['pmGallery']['Version'] = '0.1';
$RecipeInfo['pmGallery']['Date'] = '2008-07-13';

/**
* Code executed on include
*/
Markup("pmgallery", 'inline', "/\\(:pmgallery\\s*(.*?):\\)/se", "Keep(pmGallery(PSS('$1')))");
$pmGroup = PageVar($GLOBALS['pagename'], '$Group');

// Specifying $pmGallery['virtualgroups'] allows us to prevent "Page not found..." error message showing up
if (!empty($pmGallery['virtualgroups'])) {
	// group names prefixed with '+' will have group-footers auto-created
	$autogroup = in_array('+'. $pmGroup, $pmGallery['virtualgroups']);
	if ($autogroup || in_array($pmGroup, $pmGallery['virtualgroups'])) {
		// prevent "Page not found..." error message showing up in pmGallery groups
		//PageNotFound file doesn't need to exist!
		$GLOBALS['DefaultPageTextFmt'] = '(:include {$Group}.jqPageNotFound:)';

		if ($autogroup) {
			$n = $pmGroup. '.GroupFooter';
			if (!PageExists($n) && $n != $GLOBALS['pagename']) {
				## Add a custom page storage location
				$GLOBALS['PageStorePath'] = dirname(__FILE__)."/wikilib.d/{\$FullName}";
				$where = count($GLOBALS['WikiLibDirs']);
				if ($where>1) $where--;
				array_splice($GLOBALS['WikiLibDirs'], $where, 0, array(new PageStore($GLOBALS['PageStorePath'])));

				// if there is no footer for this group then copy the default over.
				WritePage($n, ReadPage('pmGallery.GroupFooter'));
			}
		}
	}
	$pmGallery['virtualgroups'][0] = trim($pmGallery['virtualgroups'][0], '+');
}

/**
* Main routine called from markup within wiki pages
*/
function pmGallery($args) {
 	$defaults = array(
		'album'			=> '',				// if album is supplied, then show images in the album, so if album is blank then show a list of albums
		'wikitarget'	=> '', 				// for albums specify the wiki group; for pages specify the group.page
		'startimg'		=> '',				// used if paging across many images, in conjunction with 'maxresults'
		'tag'				=> '',				// used to search for a tag
		'random'			=> '',				// numer of random images to include
		'width'			=> '',				// override for image display; usually set in CSS
		'height'			=> '',				// override for image display; usually set in CSS
		'imageurl'		=> '',				// Usually set automatically, and does not need to be set by user

		// These parameters may also be set globally in config.php
		'user'			=> '',
		'thumbsize'		=> '', 				// default is 72: 32, 48, 64, 72, 144, 160,200, 288, 320, 400, 512, 576, 640, 720, 800
		'imagesize'		=> '', 				// defult is 640: 32, 48, 64, 72, 144, 160,200, 288, 320, 400, 512, 576, 640, 720, 800
		'maxresults'   => '', 				// default is 50: numeric (max # images/albums)
		'wrapper'		=> 'div',			// default is 'div': '>' separated format for outter and inner html tags: 'ul > li', 'div > div' (or 'div'), etc
		'mode'			=> '',				// 'cover': shows the cover of the single album specified; 
													// 'linkdirect': links direct to source image (used with external pugins)
		'provider'		=> 'picasa',		// where the images are coming from (picasa)

		// These parameters are usually set globally in config.php
		'urlbase'		=> 'com',			// default is 'com': source of the rss feed
		'proxy'			=> '',				// specify the proxy and port ('proxy.server.com:8080')
		'authkey'		=> '',				//
		'debug'			=> false,			//
		'cachedir' 		=> '',				// location of the cache directory. Default is [directory of this file].'/cache'. NO trailing /
		'cachelife' 	=> '7200'			// default is '7200' (2 hours): set to '0' to disable the cache
	);
	$pmGallery = $GLOBALS['pmGallery'];
	$opt = array_merge($defaults,$pmGallery);
	$opt = array_merge($opt,ParseArgs($args));
	$opt = array_merge($opt,$_GET);

	$opt['wikitarget'] = (empty($opt['wikitarget']) ? $GLOBALS['pmGroup'] : $opt['wikitarget']);

	// if the image url supplied, then show the image
	if (!empty($opt['imageurl'])) {
		return '<div class="pmGalleryWrapperImage"><img src="http://'. $opt['imageurl']. '" /></div>';
	}

	require_once('picasa.php5');
	$myPicasaParser = new picasaAPI();
	$myPicasaParser->updateOption('startimg', $opt['startimg']);
	$myPicasaParser->updateOption('tag', $opt['tag']);
	$myPicasaParser->updateOption('user', $opt['user'], false);
	$myPicasaParser->updateOption('thumbsize', $opt['thumbsize'], false);
	$myPicasaParser->updateOption('imagesize', $opt['imagesize'], false);
	$myPicasaParser->updateOption('maxresults', $opt['maxresults'], false);
	$myPicasaParser->updateOption('urlbase', $opt['urlbase']);
	$myPicasaParser->updateOption('cachelife', $opt['cachelife']);
	$myPicasaParser->updateOption('cachedir', $opt['cachedir']);
	$myPicasaParser->updateOption('proxy', $opt['proxy']);
	$myPicasaParser->updateOption('authkey', $opt['authkey']);

	// parse out the HTML outter and inner wrappers "ul > li" or "div > div", etc
	$wrapper = explode('>', $opt['wrapper']);
	$wrapper[0]=trim($wrapper[0]);
	$wrapper[1]=trim($wrapper[ (empty($wrapper[1]) ? 0 : 1) ]);

	// holds the album/image sequencing; used to allow randomizing. Format: $seqA[n]='album#:image#'
	$seqA = Array();
	$albums = explode(',', $opt['album']);
	$text='';
	// displaying one or more album covers: either $opt['display'] is 'cover' or no album specified
	$displayCover = $opt['mode']=='cover' || empty($opt['album']);
	$linkDirect = $opt['mode']=='linkdirect';

	// handle procesing of more than one album, ie, (:pmGallery album=album1,album2 :)
	for ($albumN=0; ($displayCover && $albumN==0) || (!$displayCover && $albumN<count($albums)); $albumN++) {
		$albumsA[$albumN] = $myPicasaParser->parseFeed( $myPicasaParser->createFeedUrl(($displayCover ? '' : $albums[$albumN]), false) );
		genArray($seqA, $albumN, count($albumsA[$albumN]['main']));
	}

	$seqN = (empty($opt['random']) || $opt['random']>count($seqA) ? count($seqA) : $opt['random']);
	if (!empty($opt['random']) && $seqN>1) {
		randomizeArray ($seqA, $seqN);
	}
	
	// loop through the sequential album/image or the randomized album/images
	for ($i=0; $i<$seqN; $i++) {
		$x = explode(':', $seqA[$i]);
		$albumN = $x[0];
		$imageN = $x[1];
		$image = $albumsA[$albumN]['main'][$imageN];
		$entry = $albumsA[$albumN]['entry'][$imageN];
		$gphoto = $albumsA[$albumN]['gphoto'][$imageN];

		//remove filename extension, otherwise PmWiki thinks it's a group.pagename format
		$image_title = explode('.',$image['title']);

		if ( ($displayCover && in_array($gphoto['name'], $albums)) || !$displayCover) {
print($gphoto['id'].'<br>');
			$text .=
				'<'. $wrapper[1]. '>'.
				MakeLink(
					$GLOBALS['pagename'],
					($linkDirect 
						? $image['largeSrc']
						: (preg_match('!(\.|\/)!', $opt['wikitarget']) ? $opt['wikitarget'] : $image_title[0])
					), //target
					'<img src="'. $image['thumbSrc']. '" '.
						// unable to find h/w for album cover images
						($displayCover ? ''
							: 'height="'. (empty($opt['height']) ? $entry['thumbnail_h'] : $opt['height']). '"'.
							  'width="'. (empty($opt['width']) ? $entry['thumbnail_w'] : $opt['width']). '"'
						).
						' />',	//link text
					'',		//suffix
					'<a class="pmGallery'. ($displayCover ? 'Album' : 'Image'). 'Thumb" '.
						"href='\$LinkUrl".
							// add optional parameters onto the end of the link url ( &album )
							($linkDirect ? ''
								: ($displayCover
										? '&album='. htmlentities($gphoto['name'])
										: '&imageurl='. htmlentities(str_replace('http://', '', $image['largeSrc']))
									)
							).
						"' ".
						(empty($entry['description'])	? '' : 'title="'. htmlentities($entry['description']). '"').
						">\$LinkText".
					'</a>'
				).
				'</'. $wrapper[1]. ">\n";
		}
	}

 	return
		'<div class="pmGalleryWrapper">'.
		(empty($text)
			? ''
			: '<'. $wrapper[0]. " class='pmGallery". ($displayCover ? 'Album' : 'Image'). "List'>\n".
				$text.
				'</'. $wrapper[0]. '>'
		).
		'</div>';
}

/**
* populate an array with sequenced numbers upto $n
*/
function genArray (&$arr, $prefix, $n) {
	$from = count($arr);
	for ($i=0; $i<$n; $i++) {
		$arr[] = $prefix. ':'. $i;
	}
}

/**
* Returns a randomize array of $n elements based on $arr
*/
function randomizeArray (&$arr, $n) {
	srand((float) microtime() * 10000000);
	$rand_keys = array_rand($arr, $n);
	shuffle($rand_keys);
	for ($i=0; $i<$n; $i++){
		$seqA_new[] = $arr[$rand_keys[$i]];
	}
	$arr = $seqA_new;
}

/**
* Returns a randomize array of $n elements based on $arr
*/
function debugLog ($msg, $out=false) {
	if ($out || (!$out && $GLOBALS['pmGallery']['debug']) ) {
		error_log(date('r'). ' [pmGallery]: '. $msg);
	}
}
