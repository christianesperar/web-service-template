<?php 

/**
 * ProcessWire web service implementation using template
 *
 * Use template URL segment to identify what page should be returned.
 * All output is generated in JSON format. 
 *
 * @copyright Copyright (c) 2014, Christian Esperar
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 *
 * ProcessWire 2.x 
 * Copyright (C) 2013 by Ryan Cramer 
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 * 
 * http://processwire.com
 *
 */

	function constructURL($url_segments) {

		// REMOVE API KEY ON URL PREPARATION
		unset($url_segments[1]);
		
		// COMBINE THE SEGMENT TO CONSTRUCT INTO URL FORMAT
		$url = implode("/", $url_segments);

		return "/" . $url . "/";
	
	}
	
	function pageToArray(Page $page) {
		
		$protocol  = empty($_SERVER['HTTPS']) ? 'http' : 'https';
		$domain    = $_SERVER['SERVER_NAME'];

		$id   = $page->id;
 		$host = $protocol . "://" . $domain;
		$url  = wire()->config->urls->files;
		
		$outputFormatting = $page->outputFormatting;
		$page->setOutputFormatting(false);
 
 		// CHECK IF PAGE IS EXISTING AND RETURN 404 STATUS IF NOT
 		if ( ! $page->id ) {
			$data = array(
				'status'	 => 404,
				'statusText' => 'NOT FOUND',
		    );	

		    return $data;
 		}

		$data = array(
			'status'	 => 200,
			'statusText' => 'OK',
	        'created'	 => $page->created,
	        'modified' 	 => $page->modified,
	        'data' 		 => array(),
	    );
 
	  	foreach( $page->template->fieldgroup as $field ) {
	  		if($field->type instanceof FieldtypeFieldsetOpen) continue;
	        	$trim_field_name = str_replace('site_', '', $field->name);
	        	$value = $page->get($field->name); 

	        	// CONSTRUCT DATA FOR REPEATER FIELD
	        	if ( $field->type == 'FieldtypeRepeater' ) {
	        		// CONVERT STRING TO ARRAY OF REPEATER ID
	        		$ids = explode("|", $value);

	        		$data = getRepeaterFieldInfo($data, $host, $ids, $trim_field_name);
	        	} 
	        	// CONSTRUCT DATA FOR PAGE FIELD
	        	elseif ( $field->type == 'FieldtypePage' ) {
	        		$data = getPageFieldInfo($data, $trim_field_name, $value);
	        	} 
	        	// CONSTRUCT DATA FOR IMAGE FIELD
	        	elseif ( $field->type == 'FieldtypeImage' ) {
	        		$images = $field->type->sleepValue($page, $field, $value);

	        		$data = getImageFieldInfo($data, $host, $url, $id, $trim_field_name, $images);
	        	}
	        	// CONSTRUCT DATA FOR COMMENTS FIELD
	        	elseif ( $field->type == 'FieldtypeComments' ) {
					// GET ALL LIST OF ID
					$ids = str_replace('|', ',', $value);
	        		
	        		$comments = $field->type->sleepValue($page, $field, $value);

	        		$data = getCommentsFieldInfo($data, $ids, $trim_field_name, $comments);
	        	}
	        	// FALLBACK
	        	else {
	        		$data['data'][$trim_field_name] = $field->type->sleepValue($page, $field, $value);
	        	}
	  	}
 
	  	$page->setOutputFormatting($outputFormatting);
 
	  	return $data;
	  	
	}
	
	function getPageInfo($id) {

		// GET PAGES INFO
		$page = wire()->pages->get("$id");
		$page = pageToArray($page);
		
		return $page['data'];
	
	} 

	function getRepeaterFieldInfo($data, $host, $ids, $trim_field_name) {

		foreach ( $ids as $id ) {
			// GET PAGES INFO
			$page = getPageInfo($id);

			foreach ($page as $key => $value) {
				// CHECK IF REPEATER HAS SET VALUE
				if ( $value ) {
					// IF REPEATER FIELD IS IMAGE
					if ($key == 'image') {
						$data['data'][$trim_field_name][$id] = $value;
					} 
					// FALLBACK
					else {
						$data['data'][$trim_field_name][$id][$key] = $value;
					}
				}
			}
		}

		return $data;

	}

	function getPageFieldInfo($data, $trim_field_name, $id) {

		// CONVERT STRING TO ARRAY OF PAGE ID
	    $ids = explode("|", $id);
	    
	    foreach ( $ids as $key1 => $value1 ) {
			// GET PAGES INFO
			$page = getPageInfo($value1);

			foreach ($page as $key2 => $value2) {
				$data['data'][$trim_field_name][$key1][$key2] = $value2;
			}
	    }

		return $data;

	}

	function getImageFieldInfo($data, $host, $url, $id, $trim_field_name, $images) {

		// SINGLE IMAGE
		if ( count($images) == 1 ) {
			$data['data'][$trim_field_name]['path'] 	   = $host . $url . $id . "/" . $images[0]['data'];
			$data['data'][$trim_field_name]['description'] = $images[0]['description'];
		}

		return $data;

	}

	function getCommentsFieldInfo($data, $ids, $trim_field_name, $comments) {

		$total = 0;

		$result = wire('db')->query(
			"
			  SELECT comment_id, rating 
			  FROM CommentRatings 
			  WHERE comment_id IN ({$ids})
			"
		);

		// GET ALL THE RATINGS AND AVERAGE
		while ( $row = $result->fetch_assoc() ) {
	    	$comment_id = $row['comment_id'];
	    	$rating 	= $row['rating'];
	    	$total		= $total + $row['rating'];

			$ratings[$comment_id] = $rating;
	    }

		// ITERATE THROUGHT THE COMMENT LIBRRARIES AND INSERT THE RATINGS
		foreach ( $comments as $key => $value ) {
			$comment_id = $comments[$key]['id'];
			$comments[$key]['ratings'] = $ratings[$comment_id];
		}

		$data['data']['average'] = $total / count($comments);
		
		$data['data'][$trim_field_name] = $comments;

		return $data;

	}

	// GET API KEY SET ON ADMIN CONFIGURATION
	$api = $pages->get('/configuration');

	if ( ! $api->secret_key ) {
		echo 'No secret key has been set';
	}	
	// CHECK IF SET API ON CONFIGURATION IS MATCH ON GIVEN KEY ON URL SEGMENT
	elseif ( $input->urlSegment1 === $api->secret_key ) {
		header('Content-type: application/json');
		
		// RESERVE WORD FOR HOME PAGE
		if ( $input->urlSegment2 === "home" ) {
			$page = $pages->get("/");
		} else {
			$url = constructURL($input->urlSegments);
			
			$page = $pages->get("$url");
		}

		$page = pageToArray($page);
		
		echo json_encode($page);
	} else {
		echo 'Invalid secret key';
	}