<?php

namespace Drupal\tweet_post;

/**
 * Class TweeterCallService.
 */
class TweeterCallService implements TweeterCallInterface {

  /**
   * @var string
   */
  private $oauth_access_token;

  /**
   * @var string
   */
  private $oauth_access_token_secret;

  /**
   * @var string
   */
  private $consumer_key;

  /**
   * @var string
   */
  private $consumer_secret;

  /**
   * @var array
   */
  private $postfields;

  /**
   * @var string
   */
  private $getfield;

  /**
   * @var mixed
   */
  protected $oauth;

  /**
   * @var string
   */
  public $url;

  /**
   * @var string
   */
  public $requestMethod;

  /**
   * The HTTP status code from the previous request.
   *
   * @var int
   */
  protected $httpStatusCode;

  /**
   * Constructs a new TweeterCallService object.
   */
  public function __construct() {}

  /**
   * @param array $settings
   *
   * @throws \RuntimeException
   */
  public function setSettings(array $settings) {
    if (!function_exists('curl_init')) {
      throw new \RuntimeException('TweeterCallService requires cURL extension to be loaded, see: http://curl.haxx.se/docs/install.html');
    }

    if (!isset($settings['oauth_access_token'])
      || !isset($settings['oauth_access_token_secret'])
      || !isset($settings['consumer_key'])
      || !isset($settings['consumer_secret'])) {
      throw new \InvalidArgumentException('Incomplete settings passed to TweeterCallService');
    }

    $this->oauth_access_token = $settings['oauth_access_token'];
    $this->oauth_access_token_secret = $settings['oauth_access_token_secret'];
    $this->consumer_key = $settings['consumer_key'];
    $this->consumer_secret = $settings['consumer_secret'];
  }

  /**
   * Set postfields array, example: array ('screen_name' => 'J7mbo').
   *
   * @param array $array
   *  Array of parameters to send to API.
   *
   * @throws \Exception
   *  When you are trying to set both get and post fields.
   *
   * @return TweeterCallService
   *  Instance of self for method chaining.
   */
  public function setPostfields(array $array) {
    if (!is_null($this->getGetfield())) {
      throw new \Exception('You can only choose get OR post fields (post fields include put).');
    }

    if (isset($array['status']) && substr($array['status'], 0, 1) === '@') {
      $array['status'] = sprintf("\0%s", $array['status']);
    }

    foreach ($array as $key => &$value) {
      if (is_bool($value)) {
        $value = ($value === true) ? 'true' : 'false';
      }
    }

    $this->postfields = $array;

    // Rebuild oAuth.
    if (isset($this->oauth['oauth_signature'])) {
      $this->buildOauth($this->url, $this->requestMethod);
    }

    return $this;
  }

  /**
   * Set get_fields string, example: '?screen_name=J7mbo'.
   *
   * @param string $string
   *  Get key and value pairs as string.
   *
   * @throws \Exception
   *
   * @return TweeterCallService
   *  Instance of self for method chaining.
   */
  public function setGetfield($string) {
    if (!is_null($this->getPostfields())) {
      throw new \Exception('You can only choose get OR post / post fields.');
    }

    $get_fields = preg_replace('/^\?/', '', explode('&', $string));
    $params = array();

    foreach ($get_fields as $field) {
      if ($field !== '') {
        list($key, $value) = explode('=', $field);
        $params[$key] = $value;
      }
    }

    $this->getfield = '?' . http_build_query($params, '', '&');

    return $this;
  }

  /**
   * @return string
   *  $this->getfields.
   */
  public function getGetfield() {
    return $this->getfield;
  }

  /**
   * Get postfields array (simple getter)
   *
   * @return array $this->postfields
   */
  public function getPostfields() {
    return $this->postfields;
  }

  /**
   * @param string $url
   *  The API url to use. Example: https://api.twitter.com/1.1/search/tweets.json.
   * @param string $requestMethod
   *  Either POST or GET.
   *
   * @throws \Exception
   *
   * @return TweeterCallService
   *  Instance of self for method chaining/
   */
  public function buildOauth($url, $requestMethod) {
    if (!in_array(strtolower($requestMethod), array('post', 'get', 'put', 'delete'))) {
      throw new \Exception('Request method must be either POST, GET or PUT or DELETE');
    }

    $consumer_key = $this->consumer_key;
    $consumer_secret = $this->consumer_secret;
    $oauth_access_token = $this->oauth_access_token;
    $oauth_access_token_secret = $this->oauth_access_token_secret;

    $oauth = array(
      'oauth_consumer_key' => $consumer_key,
      'oauth_nonce' => time(),
      'oauth_signature_method' => 'HMAC-SHA1',
      'oauth_token' => $oauth_access_token,
      'oauth_timestamp' => time(),
      'oauth_version' => '1.0'
    );

    $getfield = $this->getGetfield();

    if (!is_null($getfield)) {
      $getfields = str_replace('?', '', explode('&', $getfield));

      foreach ($getfields as $g) {
        $split = explode('=', $g);

        // In case a null is passed through.
        if (isset($split[1])) {
          $oauth[$split[0]] = urldecode($split[1]);
        }
      }
    }

    $postfields = $this->getPostfields();

    if (!is_null($postfields)) {
      foreach ($postfields as $key => $value) {
        $oauth[$key] = $value;
      }
    }

    $base_info = $this->buildBaseString($url, $requestMethod, $oauth);
    $composite_key = rawurlencode($consumer_secret) . '&' . rawurlencode($oauth_access_token_secret);
    $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
    $oauth['oauth_signature'] = $oauth_signature;

    $this->url = $url;
    $this->requestMethod = $requestMethod;
    $this->oauth = $oauth;

    return $this;
  }

  /**
   * Perform the actual data retrieval from the API.
   *
   * @param boolean $return
   *  If true, returns data. This is left in for backward compatibility reasons.
   * @param array $curlOptions
   *  Additional Curl options for this request.
   *
   * @throws \Exception
   *
   * @return string
   *  json If $return param is true, returns json data.
   */
  public function performRequest($return = true, $curlOptions = array()) {
    if (!is_bool($return)) {
      throw new \Exception('performRequest parameter must be true or false');
    }

    $header =  array($this->buildAuthorizationHeader($this->oauth), 'Expect:');

    $getfield = $this->getGetfield();
    $postfields = $this->getPostfields();

    if (in_array(strtolower($this->requestMethod), array('put', 'delete'))) {
      $curlOptions[CURLOPT_CUSTOMREQUEST] = $this->requestMethod;
    }

    $options = $curlOptions + array(
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_HEADER => false,
        CURLOPT_URL => $this->url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
      );

    if (!is_null($postfields)) {
      $options[CURLOPT_POSTFIELDS] = http_build_query($postfields, '', '&');
    }
    else {
      if ($getfield !== '') {
        $options[CURLOPT_URL] .= $getfield;
      }
    }

    $feed = curl_init();
    curl_setopt_array($feed, $options);
    $json = curl_exec($feed);

    $this->httpStatusCode = curl_getinfo($feed, CURLINFO_HTTP_CODE);

    if (($error = curl_error($feed)) !== '') {
      curl_close($feed);

      throw new \Exception($error);
    }

    curl_close($feed);

    return $json;
  }

  /**
   * Private method to generate the base string used by cURL.
   *
   * @param string $baseURI
   * @param string $method
   * @param array  $params
   *
   * @return string
   *  Built base string.
   */
  private function buildBaseString($baseURI, $method, $params) {
    $return = array();
    ksort($params);

    foreach($params as $key => $value) {
      $return[] = rawurlencode($key) . '=' . rawurlencode($value);
    }

    return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $return));
  }

  /**
   * Private method to generate authorization header used by cURL.
   *
   * @param array $oauth
   *  Array of oauth data generated by buildOauth().
   *
   * @return string
   *  $return Header used by cURL for request.
   */
  private function buildAuthorizationHeader(array $oauth) {
    $return = 'Authorization: OAuth ';
    $values = array();

    foreach($oauth as $key => $value) {
      if (in_array($key, array('oauth_consumer_key', 'oauth_nonce', 'oauth_signature',
        'oauth_signature_method', 'oauth_timestamp', 'oauth_token', 'oauth_version'))) {
        $values[] = "$key=\"" . rawurlencode($value) . "\"";
      }
    }
    $return .= implode(', ', $values);

    return $return;
  }

  /**
   * Helper method to perform our request.
   *
   * @param string $url
   * @param string $method
   * @param string $data
   * @param array  $curlOptions
   *
   * @throws \Exception
   *
   * @return string
   *  The json response from the server.
   */
  public function request($url, $method = 'get', $data = null, $curlOptions = array()) {
    if (strtolower($method) === 'get') {
      $this->setGetfield($data);
    }
    else {
      $this->setPostfields($data);
    }

    return $this->buildOauth($url, $method)->performRequest(true, $curlOptions);
  }

  /**
   * Get the HTTP status code for the previous request.
   *
   * @return integer
   */
  public function getHttpStatusCode() {
    return $this->httpStatusCode;
  }

}
