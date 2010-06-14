<?php if (!defined('PmWiki')) exit();
/*  Copyright 2008 David Gilbert ( http://solidgone.org/lightweight-php-picasa-api )
    You can redistribute this file and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
 *
 * based on: http://www.ebugz.de/picasa-api-and-php/
 * Class evaluates Google Picasa Atom feeds and transforms it to usable PHP
 */

class picasaAPI{

	private $options = array (
		'user' => '',							//Username that is a valid Google Picasa Account
		'thumbsize' => '72',					//32, 48, 64, 72, 144, 160,200, 288, 320, 400, 512, 576, 640, 720, 800
		'imagesize' => '640',				//32, 48, 64, 72, 144, 160,200, 288, 320, 400, 512, 576, 640, 720, 800
		'maxresults' => '50',
		'starting' => '',
		'tag' => '',
		'query' => '',							//Query string (for Picasa, set to "" to order descending by date, like an RSS feed)
		'urlbase' => 'com',					//base domain name (com,fr,de...)
		'cachedir' => '',						//location of the cache directory. Default is [directory of this file].'/cache'. NO trailing /
		'cachelife' => '7200'
	);
	public function updateOption($opt, $val, $canBeEmpty=true){
		if(!empty($opt) && ($canBeEmpty || (!$canBeEmpty && !empty($val)) ) ){
			$this->options[$opt] = $val;
			return true;
		}
		return false;
	}

	//Error Codes change it to your language
	public $errorCodes = array(
		1 => "Photos successfully imported",
		2 => "This Username does not exist at Picasa",
		3 => "error ocurred - please try later again"
	);

	private function getPicasaurlbase(){
		return 'http://picasaweb.google.'.$this->options['urlbase'].'/data/feed/api/user/'.$this->options['user'];
	}
	/**
	* method creates a valid url to access a users Albumlist
	* $album can either be the album name or the album ID as returned by the API (set $isID=true).
	* http://picasaweb.google.com/data/feed/api/user/nepherim?kind=photo&max-results=50&thumbsize=72&tag=unphotographed,moon
	*/
	public function createFeedUrl($album,$isID){
		debugLog('Album: '.$album.' |isID: '.$isID.' |User: '.$this->options['user']);
		if(!empty($this->options['user'])){
			if ( empty($album) && empty($this->options['tag']) && empty($this->options['query']) ) {
				$feedUrl = '?kind=album';
			} else {
				$feedUrl = (empty($album)	? '' : '/album'. (isset($isID) && $isID ? 'id' : ''). '/'. $album). '?kind=photo'
					.'&q='.$this->options['query'];
			}
			$feedUrl = $this->getPicasaurlbase(). $feedUrl.
				(empty($this->options['maxresults'])?'':'&max-results='.$this->options['maxresults']).
				'&start-index=' .(empty($this->options['starting'])?1:$this->options['starting']).
				(empty($this->options['thumbsize'])?'':'&thumbsize='.$this->options['thumbsize']).
				(empty($this->options['authkey'])?'':'&authkey='.$this->options['authkey']).
				(empty($this->options['tag'])?'':'&tag='.$this->options['tag']);
			debugLog('feedUrl: '.$feedUrl);
			return $feedUrl;
		}
		return false;
	}

	/**
	* Thumbnailed image url's are domainurl/sXXX/image.ext. Need to add the sXXX component to normal url's
	*/
	private function getThumbnailUrl($imgSrc, $imgSize){
		$img = explode('/', $imgSrc);
		array_splice($img, count($img)-1, 0, 's'.$imgSize);	// insert 'sXXX' as 2nd last element
		$thmbSrc = implode('/', $img);
		return (string)$thmbSrc;
	}

	/**
	 * key method that parses the XML API stream and converts to PHP
	 * has to be called with 1 argument: the ressource that should be evaluated
	 * e.g. "http://picasaweb.google.com/data/feed/api/user/USERNAME?kind=album"
	 */
	public function parseFeed($location) {

		require_once('feeder.php');
		$feed_arr = array(); //Array that contains all loaded and parsed Data
		$feedXml = readFeed($location, $this->options['cachelife'], $this->options['cachedir'], $this->options['proxy']);

		debugLog('Feed (' .$location .')');
		if (empty($feedXml)) {
			debugLog('pmGallery Error: Unable to retrieve feed (' .$location .')', true);
			return $feed_arr;
		}

		// parse the RSS Feed -- needs PHP5
		$feed = simplexml_load_string($feedXml);
		$namespace = $feed->getDocNamespaces();

		//Main user information
		$feed_arr['user']['name'] = (string)$feed->author->name;
		$feed_arr['user']['icon'] = (string)$feed->icon;
		$feed_arr['user']['id'] = (string)$feed->id;

		$i = 0;
		foreach ($feed->entry as $item) {
			$feed_arr['main'][$i]['id'] =(string) $item->id;
			$feed_arr['main'][$i]['title'] = (string)$item->title;
			$feed_arr['main'][$i]['src'] = (string)$item->content['src'];
			$feed_arr['main'][$i]['apiLink'] = (string)$item->link[0]['href'];
			$feed_arr['main'][$i]['published'] = (string)$item->published;
			$feed_arr['main'][$i]['updated'] = (string)$item->updated;
			$feed_arr['main'][$i]['summary'] = (string)$item->summary;
			$feed_arr['main'][$i]['rights'] = (string)$item->rights;
			$feed_arr['main'][$i]['author'] = (string)$item->author->name;
			$feed_arr['main'][$i]['authorLink'] = (string)$item->author->uri;

			//Gphoto namespace data
			$ns_gphoto=$item->children($namespace['gphoto']);
			$feed_arr['gphoto'][$i]['id'] = (string)$ns_gphoto->id;
			$feed_arr['gphoto'][$i]['name'] = (string)$ns_gphoto->name;
			$feed_arr['gphoto'][$i]['timestamp'] = (string)$ns_gphoto->timestamp/1000;
			$feed_arr['gphoto'][$i]['albumid'] = (string)$ns_gphoto->id;
			$feed_arr['gphoto'][$i]['numphotos'] = (string)$ns_gphoto->numphotos;
			$feed_arr['gphoto'][$i]['commentingEnabled'] = (string)$ns_gphoto->commentingEnabled;
			$feed_arr['gphoto'][$i]['commentCount'] = (string)$ns_gphoto->commentCount;
			$feed_arr['gphoto'][$i]['location'] = (string)$ns_gphoto->location;
			$feed_arr['gphoto'][$i]['height'] = (string)$ns_gphoto->height;
			$feed_arr['gphoto'][$i]['width'] = (string)$ns_gphoto->width;

			//Media namespace data
			$ns_media=$item->children($namespace['media']);
			$feed_arr['entry'][$i]['id'] = (string)$ns_gphoto->id;
			$feed_arr['entry'][$i]['title'] = (string)$ns_media->group->title;
			$feed_arr['entry'][$i]['description'] = (string)$ns_media->group->description;
			$feed_arr['entry'][$i]['keywords'] = (string)$ns_media->group->keywords;

			$thumb_attr = $ns_media->group->thumbnail->attributes();
			$feed_arr['entry'][$i]['thumbnail_w'] = (int)$thumb_attr['width'];
			$feed_arr['entry'][$i]['thumbnail_h'] = (int)$thumb_attr['height'];

			$con_attr = $ns_media->group->content->attributes();
			$feed_arr['entry'][$i]['url'] = (string)$con_attr['url'];
			$feed_arr['entry'][$i]['width'] = (string)$con_attr['width'];
			$feed_arr['entry'][$i]['height'] = (string)$con_attr['height'];
			$feed_arr['entry'][$i]['type'] = (string)$con_attr['type'];

			//Exif namespace data
			$exif=$item->children($namespace['exif'])->tags;
			$feed_arr['exif'][$i]['distance'] = (string)$exif->distance;
			$feed_arr['exif'][$i]['exposure'] = (string)$exif->exposure;  //"1/" + (int)(1 / value + 0.5);
			$feed_arr['exif'][$i]['flash'] = (string)$exif->flash;
			$feed_arr['exif'][$i]['focallength'] = (string)$exif->focallength;
			$feed_arr['exif'][$i]['fstop'] = (string)$exif->fstop;
			$feed_arr['exif'][$i]['imageUniqueID'] = (string)$exif->imageUniqueID;
			$feed_arr['exif'][$i]['iso'] = (string)$exif->iso;
			$feed_arr['exif'][$i]['make'] = (string)$exif->make;
			$feed_arr['exif'][$i]['model'] = (string)$exif->model;
			$feed_arr['exif'][$i]['tags'] = (string)$exif->tags;
			$feed_arr['exif'][$i]['timestamp'] = (string)$exif->time;

			$feed_arr['main'][$i]['thumbSrc'] = $this->getThumbnailUrl($feed_arr['entry'][$i]['url'], $this->options['thumbsize']);
			$feed_arr['main'][$i]['largeSrc'] = $this->getThumbnailUrl($feed_arr['entry'][$i]['url'], $this->options['imagesize']);

			$i++;
		}
		return $feed_arr;
	}
}
