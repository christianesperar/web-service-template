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

class WebService
{
    protected $host;
    protected $fieldPrefix;

    public function __construct($fieldPrefix)
    {
        $this->host = $this->getHost();
        $this->fieldPrefix = $fieldPrefix;
    }

    public function getHost() {
        $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';
        $domain = $_SERVER['SERVER_NAME'];

        return $protocol . "://" . $domain;
    }

    public function constructURL($urlSegments) {
        // REMOVE API KEY ON URL PREPARATION
        unset($urlSegments[1]);
        
        // COMBINE THE SEGMENT TO CONSTRUCT INTO URL FORMAT
        $url = implode("/", $urlSegments);

        return "/" . $url . "/";
    }

    public function pageToArray(Page $page, $data = array()) {
        $url = wire()->config->urls->files;

        $outputFormatting = $page->outputFormatting;
        $page->setOutputFormatting(false);

        $id = $page->id;

        // CHECK IF PAGE IS EXISTING AND RETURN 404 STATUS IF NOT
        if ( ! $id ) {
            return array(
                'status'     => 404,
                'statusText' => 'NOT FOUND',
            );
        }

        $data = array(
            'status'     => 200,
            'statusText' => 'OK',
            'created'    => $page->created,
            'modified'   => $page->modified,
            'data'       => $this->getAdditionalPageField($page, $data),
        );

        foreach( $page->template->fieldgroup as $field ) {
            if($field->type instanceof FieldtypeFieldsetOpen) continue;

                // HIDE FIELD PREFIX
                $trimFieldName = str_replace($this->fieldPrefix, '', $field->name);

                $value = $page->get($field->name); 

                switch ( $field->type ) {
                    // CONSTRUCT DATA FOR REPEATER FIELD
                    case 'FieldtypeRepeater':
                        // CONVERT STRING TO ARRAY OF REPEATER ID
                        $ids = explode("|", $value);
                        
                        $data = $this->getRepeaterFieldInfo($data, $ids, $trimFieldName);

                        break;

                    // CONSTRUCT DATA FOR PAGE FIELD
                    case 'FieldtypePage':
                        $data = $this->getPageFieldInfo($data, $trimFieldName, $value);

                        break;

                    // CONSTRUCT DATA FOR IMAGE FIELD
                    case 'FieldtypeImage':
                        $images = $field->type->sleepValue($page, $field, $value);

                        $data = $this->getImageFieldInfo($data, $url, $id, $trimFieldName, $images);

                        break;

                    // CONSTRUCT DATA FOR COMMENTS FIELD
                    case 'FieldtypeComments':
                        // GET ALL LIST OF ID
                        $ids = str_replace('|', ',', $value);

                        $comments = $field->type->sleepValue($page, $field, $value);

                        $data = $this->getCommentsFieldInfo($data, $ids, $trimFieldName, $comments);

                        break;

                    // FALLBACK             
                    default:
                        $data['data'][$trimFieldName] = $field->type->sleepValue($page, $field, $value);

                        break;
                }
        }

        $page->setOutputFormatting($outputFormatting);

        return $data;
    }

    public function getPageInfo($id, $data = array()) {
        // GET PAGES INFO
        $page = wire()->pages->get("$id");
        $page = $this->pageToArray($page, $data);

        return isset( $page['data'] ) ? $page['data'] : null;
    }

    public function getRepeaterFieldInfo($data, $ids, $trimFieldName) {
        foreach ( $ids as $id ) {
            // GET PAGES INFO
            $page = $this->getPageInfo($id);

            if ( $page ) {
                foreach ($page as $key => $value) {
                    // CHECK IF REPEATER HAS SET VALUE
                    if ( $value ) {
                        // IF REPEATER FIELD IS IMAGE
                        if ($key == 'image') {
                            $data['data'][$trimFieldName][$id] = $value;
                        } 
                        // FALLBACK
                        else {
                            $data['data'][$trimFieldName][$id][$key] = $value;
                        }
                    }
                }
            }
        }

        return $data;
    }

    public function getPageFieldInfo($data, $trimFieldName, $id) {
        // CONVERT STRING TO ARRAY OF PAGE ID
        $ids = explode("|", $id);

        foreach ( $ids as $id ) {
            // GET PAGES INFO
            $page = $this->getPageInfo($id, ['path']);

            if ( $page ) {
                foreach ($page as $key => $value) {
                    $data['data'][$trimFieldName][$key] = $value;
                }
            }
        }

        return $data;
    }

    public function getImageFieldInfo($data, $url, $id, $trimFieldName, $images) {
        foreach ( $images as $key => $value ) {
            $data['data'][$trimFieldName][$key] = array(
                'path'        => $this->host . $url . $id . "/" . $value['data'],
                'description' => $value['description']
            );
        }

        return $data;

    }

    public function getCommentsFieldInfo($data, $ids, $trimFieldName, $comments) {

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
            $commentID = $row['comment_id'];
            $rating = $row['rating'];
            $total  = $total + $row['rating'];

            $ratings[$commentID] = $rating;
        }

        // ITERATE THROUGHT THE COMMENT LIBRRARIES AND INSERT THE RATINGS
        foreach ( $comments as $key => $value ) {
            $commentID = $comments[$key]['id'];
            $comments[$key]['ratings'] = $ratings[$commentID];
        }

        $data['data']['average'] = $total / count($comments);
        
        $data['data'][$trimFieldName] = $comments;

        return $data;

    }

    public function getAdditionalPageField($page, $fields) {
        $data = array();

        foreach ($fields as $field) {
            $data[$field] = $page->$field;
        }

        return $data;
    }
}

// GET API KEY SET ON ADMIN CONFIGURATION
$api = $pages->get('/configuration');

if ( ! $api->secret_key ) {
    echo 'No secret key has been set';
}   
// CHECK IF SET API ON CONFIGURATION IS MATCH ON GIVEN KEY ON URL SEGMENT
elseif ( $input->urlSegment1 === $api->secret_key ) {
    $urlSegments = $input->urlSegments;

    if ( !$config->debug ) header('Content-type: application/json');
    
    // GET SET FIELD PREFIX
    $fieldPrefix = $api->field_prefix;

    $webService = new WebService($fieldPrefix);

    // RESERVE WORD FOR HOME PAGE
    if ( $input->urlSegment2 === "home" ) {
        $page = $pages->get("/");
    } else {
        $url = $webService->constructURL($urlSegments);
        
        $page = $pages->get("$url");
    }
    
    echo json_encode($webService->pageToArray($page, array('path')));
} else {
    echo 'Invalid secret key';
}