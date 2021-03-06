<?php
// *********************************************************************
//
//      Class MYOB API OAUTH
//
//          Written by Grigor Farishyan
//           Date: Oct 2014
//
//      Provided as sample oauth class for PHP & cURL OAUTH
//
//
// ********************************************************************

class myob_api_oauth {

  private $version = 'v2';
  private $api_secret = '';
  private $scope = 'CompanyFile';
  private $redirect_url = '';
  private $api_key = '';
  private $auth_url = 'https://secure.myob.com/oauth2/v1/authorize';
  private $access_code = '';
  private $response = '';
  private $access_token  = '';
  private $token_type  = '';
  private $token_expires_in  = '';
  private $refresh_token  = '';

  private $username;
  private $company_username = '';
  private $company_password  = '';
  private $uid;
  private $guid = '';
  public $error = array();
  private $is_post = FALSE;
  private $curl_request_method = 'get';

  const COMPANY_BASE_URL = 'https://api.myob.com/accountright/';
  public function __construct($params = array()) {
    if (!empty($params)) {
      foreach ($params as $property => $value) {
        $this->__set($property, $value);
      }
    }
  }

  public function __set($property, $value) {
    if (property_exists($this, $property)) {
      if (is_array($this->$property)) {
        $this->{$property}[] = $value;
      } else {
        $this->{$property} = $value;
      }
    } else {
      $this->{$property} = $value;
    }
  }

  public function __get($property) {
    return $this->{$property};
  }

  /**
   * @param $method - e.g. post, get, put, delete
   * @param $name - function to call
   * first name is a function result will be get_functionname
   * @param $arguments - arguments as list
   * @return mixed
   */
  public function call($method, $name, $arguments = array()) {
     $method_name = $method . '_' .$name;
     if (method_exists($this, $method_name)) {
      return $this->{$method_name}($arguments);
     } else {
       $this->__set('error', 'method does not exists');
       return $this->__get('error');
     }
  }

  public function getAccessToken() {
    $params = array(
      'client_id' => $this->__get('api_key'),
      'client_secret' => $this->__get('api_secret'),
      'scope' => $this->__get('scope'),
      'code' => $this->__get('access_code'),
      'redirect_uri' => $this->__get('redirect_url'),
      'grant_type' => 'authorization_code',
    );

    $this->curl_request_method = 'post';
    $this->getURL('https://secure.myob.com/oauth2/v1/authorize', $params, null);

    if (empty($this->error)) {
      $this->__set('access_token' , $this->response['access_token']);
      $this->__set('token_type', $this->response['token_type']);
      $this->__set('token_expires_in', $this->response['expires_in']);
      $this->__set('refresh_token', $this->response['refresh_token']);
      $this->__set('username', $this->response['user']['username']);
      $this->__set('uid', $this->response['user']['uid']);
    }
    return $this->response;
  }

  private function build_auth_header() {
    $cftoken = '';
    $u = $this->__get('company_username');
    $pwd = $this->__get('company_password');
    if (!empty($u)) {
      $cftoken = base64_encode(sprintf("%s:%s", $u, $pwd));
    }

    $header = array(
      'Authorization: ' . sprintf("%s %s", ucfirst($this->__get('token_type')), $this->__get('access_token')),
      'x-myobapi-cftoken: ' . $cftoken,
      'x-myobapi-key: ' . $this->__get('api_key'),
      'x-myobapi-version: ' . $this->__get('version'),
      'Accept-Encoding: '
    );

    return $header;
  }

  public function refreshAccessToken() {
    $params = array(
      'client_id'  =>	$this->__get('api_key'),
      'client_secret' =>	$this->__get('api_secret'),
      'refresh_token' =>	$this->__get('refresh_token'),
      'grant_type' =>	'refresh_token', // refresh_token -> refreshes your access token
    );
    $this->curl_request_method = 'post';
    $this->getURL('https://secure.myob.com/oauth2/v1/authorize', $params, null);
    if (empty($this->error)) {
      $this->__set('access_token', $this->response['access_token']);
      $this->__set('token_type', $this->response['token_type']);
      $this->__set('token_expires_in', $this->response['expires_in']);
      $this->__set('refresh_token', $this->response['refresh_token']);
    }
    return $this->response;
  }

  // private function for CURL
  private function getURL($url, $params = array(), $headers=null) {
    $this->error = array();
    $ch = curl_init($url);
    if( !empty($headers) ) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $q = '';

    /* build curl custom request */
    $custom_request = strtoupper($this->curl_request_method);
    curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, $custom_request);
    //curl_setopt ($ch, CURLOPT_POST_FIELDS, true);
    if (is_array($params)) {
      $q = http_build_query($params);
    } else {
      $q = $params;
    }
    curl_setopt ($ch, CURLOPT_POSTFIELDS, $q);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $r = curl_exec($ch);

    $info = curl_getinfo($ch);
    $this->debug[] = array(
      'headers' => $headers,
      'curl' => $info,
      'params' => $q
    );
    $error = curl_error($ch);
    if ($error) {
      $this->error['curl'][curl_errno($ch)] = $error;
    } elseif ($info['http_code'] !== 200) {
      $this->error['server'][] = $info['http_code'];
    }
    curl_close($ch);

    $r = json_decode($r, TRUE);
    if (isset($r['error'])) {
      $this->error['api'][] = $r['error'];
    }

    $this->response = $r;
  }

  /**
   * @param $endpoint -
   * @param array $args
   */
  public function get($endpoint, $args=array()) {
    /**
     * Build endpoint
     *
     */
     $header = $this->build_auth_header();
     $this->is_post = FALSE;
     $this->curl_request_method = 'get';
     $new_endpoint = '/' . str_replace('_', '/', $endpoint);
     $url = self::COMPANY_BASE_URL . $this->__get('guid') . $new_endpoint;
     if (!empty($args['query'])) {
       $url .= '?' . http_build_query($args['query']);
     }

     $this->getURL($url, array(), $header);

     return $this->response;
  }

  public function get_companyFiles($args = array()) {
    $header = $this->build_auth_header();
    $this->is_post = FALSE;
    $this->getURL(self::COMPANY_BASE_URL, array(), $header);
    if (!empty($this->error)) {
      return array();
    }
    return $this->response;
  }
}
