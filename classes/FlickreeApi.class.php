<?php

/**
 *
 * Flickr 
 *
 */

abstract class FlickreeApi {

  protected $api_key;
  protected $method;
  
  /**
   * Setters and getters 
   */
  public function __get($prop) {
    if (in_array($prop, $this->_params)) return $this->$prop;
  }
  
  public function __set($prop, $val) {
      // size has human-readable values that need to be translated to flickr values
      if ($prop==='size'){ 
        $map = array('square'=>'url_sq', 'thumbnail'=>'url_t', 'small'=>'url_s', 'medium'=>'url_m',
                     'original'=>'url_o');
        $this->$prop = $map[$val];
      }
      if ($prop==='display'){
        $this->per_page = $val; 
      }
      else {
        if (in_array($prop, $this->_params)) $this->$prop = $val;
      }
  }
  
  protected $_params = array('size', 'per_page', 'privacy_filter', 'photo_id', 'photoset_id',
      'extras', 'user_id', 'tags', 'tag_mode', 'text', 'min_upload_date',
      'max_upload_date', 'min_taken_date', 'max_taken_date', 'sort', 'bbox',
      'accuracy', 'safe_search', 'content_type', 'machine_tags', 'machine_tag_mode',
      'group_id', 'contacts', 'woe_id', 'place_id', 'media', 'has_geo', 'geo_context',
      'lat', 'lon', 'radius', 'radius_units', 'is_commons', 'in_gallery', 'is_getty',
      'format', 'nojsoncallback', 'api_key', 'method', 'url', 'gallery_id', 'group_id');
  
  /**
   * callAPI
   * 
   * Build the URL based on the params, return the result
   * @return array of FlickrPhoto objects
   * TODO: implement random, implement display attribute, how does getPhoto set the size
   *
   */
  public function callAPI() {

    try {

      $url = 'http://api.flickr.com/services/rest/';
      
      // required params
      $url.='?method=' . $this->method;
      $url.='&api_key=' . $this->api_key;

      foreach ($this->_params as $param) {
        if ($param==='method' || $param==='api_key' || $param==='format' || $param==='nojsoncallback'){
          continue;
        }
        if (isset($this->$param)) {
          $url.='&' . $param . '=' . urlencode($this->$param);
        }
      }
      
      // Data format
      $format = (isset($this->format)) ? $this->format : 'json';
      $nojsoncallback = (isset($this->nojsoncallback)) ?  $this->nojsoncallback : '1';
      
      $url .= '&format=' . $format;
      $url .= '&nojsoncallback=' . $nojsoncallback;
      
      if ($format == 'php_serial') 
        $result = unserialize(file_get_contents($url));
      else if ($format == 'json')
        $result = json_decode(file_get_contents($url), true);

      if (!$result)
        throw new Exception('Could not connect');
    } catch (Exception $e) {
      $flickree_options = get_option('flickree_options');
      
      if($flickree_options['report']){

        // Hash of report to prevent spamming the developer (me!)
        $error_id = md5($url);
        if ( (!isset($_SESSION['flickree']['report'])) || (!is_array($_SESSION['flickree']['report'])) ) {
          $_SESSION['flickree']['report'] = array();
        }
        if (in_array($error_id, $_SESSION['flickree']['report']) ) {
          $mailsent= '. Email already sent to developer';
        }
        else {
          $serverinfo = print_r($_SERVER, true);
          $file = $e->getFile();
          $style = 'style=\'font-size:12px; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;\'';

$msg = <<<EOF
<h1>Flickree error report</h1>
<p $style><strong>Error ID </strong>$error_id</p>
<p $style><strong>URL </strong>$url</p>
<p $style><strong>Server </strong><pre $style>$serverinfo</pre></p>
<p $style><strong>file </strong>$file</p>
EOF;

          $headers = array();
          if ($cc = $flickree_options['cc']){
            $headers[] = 'Cc: '.$cc;
          }
          add_filter('wp_mail_content_type', create_function('', 'return "text/html";'));
          if( wp_mail( 'flickree@bcooling.com.au', 'flickree connection error report', $msg, $headers ) ){
            $mailsent= '. Email sent to developer';
            $_SESSION['flickree']['report'][] = $error_id;
          }

        }

      } // endif send email report

      $mailsent = (isset($mailsent)) ? $mailsent : '';
      return array('status' => 'failure', 'msg' => $e->getFile() . ' could not proccess webservice' . $mailsent);
    }

    switch ($this->method) {
      case 'flickr.photosets.getPhotos':
        $data = $result['photoset']['photo'];
        break;
      default:
        $data = $result;
    }

    if ($result['stat'] === 'ok')
      return array('status' => 'success', 'data' => $data);
    else
      return array('status' => 'failure', 'msg' => $result['message']);
  }

    /**
     * Normalises the resulting array into an array of FlickrPhotos
     * 
     * @param array $photos Array returned from API method result
     * @return array Array of normalised FlickrPhotos
     * 
     */
    public function getFlickreePhotoArray($photos){
      $flickreePhotos = array();
      foreach ($photos as $photo) {
        $myFlickrPhoto = $this->getFlickrPhoto($photo);
        $flickreePhoto = $myFlickrPhoto->_toArray();
        array_push($flickreePhotos, $flickreePhoto);
      }
      return $flickreePhotos;
    }

    public function getFlickrPhoto($photo){
      $myFlickrPhoto = new FlickreePhoto();
      foreach($myFlickrPhoto->params as $param){
        if ($param==='description'){
          $myFlickrPhoto->$param = $photo[$param]['_content'];
        }
        else {
          $myFlickrPhoto->$param = (array_key_exists($param, $photo)) ? $photo[$param] : '';
        }
      }
      return $myFlickrPhoto;
    }

}