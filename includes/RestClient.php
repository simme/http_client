<?php

class RestClient {
  private $authentication = NULL;
  private $request_alter = NULL;
  private $formatter = NULL;
  private $lastError = FALSE;
  public $rawResponse;
  public $lastResponse;

  /**
   * Creates a Rest client
   *
   * @param string $authentication
   * @param string $formatter
   * @param string $request_alter
   */
  public function __construct($authentication = NULL, $formatter = NULL, $request_alter = NULL) {
    $this->setAuthentication($authentication);
    $this->setFormatter($formatter);
    $this->setRequestAlter($request_alter);
  }

  /**
   * Inject authentication class
   * @param RestClientAuthentication $auth
   *   The class to use for authentication. Must implement RestClientAuthentication
   *
   * @return void
   * @throws InvalidArgumentException
   */
  public function setAuthentication($auth) {
    // Validate authenticator class
    if (!$authentication || ($authentication instanceof RestClientAuthentication)) {
      $this->authentication = $authentication;
    }
    else {
      throw new InvalidArgumentException(
        'The authentication paramater must either be an object implementing RestClientAuthentication or evaluate to fALSE.'
      );
    }
  }

  /**
   * Inject formatter class
   * @param RestClientFormatter $formatter
   *   The class to use for formatting. Must implement RestClientFormatter
   *
   * @return void
   * @throws InvalidArgumentException
   */
  public function setFormatter($formatter) {
    // Validate formatter
    if (!$formatter || ($formatter instanceof RestClientFormatter)) {
      $this->formatter = $formatter;
    }
    else {
      throw new InvalidArgumentException(
        'The formatter parameter must either be an object implementing RestClientFormatter or evaluate to FALSE.'
      );
    }
  }

  /**
   * Inject formatter class
   * @param RestClientFormatter $formatter
   *   The class to use for formatting. Must implement RestClientFormatter
   *
   * @return void
   * @throws InvalidArgumentException
   */
  public function setRequestAlter($request_alter) {
    // Validate request alterer
    if (is_object($request_alter)) {
      $request_alter = array($request_alter, 'alterRequest');
    }
    if (!$request_alter || is_callable($request_alter)) {
      $this->request_alter = $request_alter;
    }
    else {
      throw new InvalidArgumentException(
        'The request_alter parameter must either be an object with a public alterRequest method, array with (object, method) or evaluate to FALSE.'
      );
    }
  }
  
  /**
   * Performs a get request against $url with $parameters
   *
   * @param string $url
   * @param array $parameters
   *
   * @return object response
   */
  public function get($url, $parameters) {
    $ch = $this->curl($url, $parameters, 'GET');
    return $this->execute($ch);
  }
  
  /**
   * Performs a post request against $url with $data and $parameters
   *
   * @param string $url
   * @param RestClientData $data
   * @param array $parameters
   *
   * @return object response
   */
  public function post($url, $data, $parameters = array()) {
    $ch = $this->curl($url, $parameters, 'POST', $data);
    return $this->execute($ch);
  }

  /**
   * Performs a put request against $url with $data and $parameters
   *
   * @param string $url
   * @param RestClientData $data
   * @param array $parameters
   *
   * @return object response
   */
  public function put($url, $data, $parameters = array()) {
    $ch = $this->curl($url, $parameters, 'PUT', $data);
    return $this->execute($ch);
  }

  /**
   * Performs a delete request against $url with $parameters
   *
   * @param string $url
   * @param array $parameters
   *
   * @return object response
   */
  public function delete($url, $parameters = array()) {
    $ch = $this->curl($url, $parameters, 'DELETE');
    return $this->execute($ch);
  }

  public function curl($url, $parameters, $method, $data=NULL, $content_type='application/vnd.php.serialized', $extra_headers=array()) {
    $ch = curl_init();

    if ($this->formatter && $data) {
      $data = $this->formatter->serialize($data);
    }

    $req = new RestClientRequest(array(
      'method' => $method,
      'url' => $url,
      'parameters' => $parameters,
      'data' => $data,
    ));
    if ($data) {
      $req->setHeader('Content-type', $content_type);
      $req->setHeader('Content-length', strlen($data));
    }

    // Allow the request to be altered
    if ($this->request_alter) {
      $this->request_alter->alterRequest($req);
    }

    // Allow the authentication implementation to do it's magic
    if ($this->authentication) {
      $this->authentication->authenticate($req);
    }

    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $req->getMethod());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $req->toUrl());
    if ($req->hasData()) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $req->getData());
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($req->getHeaders(), $extra_headers));
    return $ch;
  }

  public function execute($ch, $unserialize = TRUE) {
    $this->rawResponse = curl_exec($ch);
    $res = $this->interpretResponse($this->rawResponse);
    $this->lastResponse = $res;
    $this->lastError = curl_error($ch);
    curl_close($ch);

    if ($res->responseCode==200) {
      if ($this->formatter) {
        return $this->formatter->unserialize($res->body);
      }
      return $res->body;
    }
    else {
      // Add better error reporting
      if (empty($res->rawResponse)) {
        throw new Exception('Curl Error: ' . $this->lastError);
      }
      throw new Exception($res->responseMessage, $res->responseCode);
    }
  }

  private function interpretResponse($res) {
    list($headers, $body) = preg_split('/\r\n\r\n/', $res, 2);

    $obj = (object)array(
      'headers' => $headers,
      'body' => $body,
    );

    $matches = array();
    if (preg_match('/HTTP\/1.\d (\d{3}) (.*)/', $headers, $matches)) {
      $obj->responseCode = trim($matches[1]);
      $obj->responseMessage = trim($matches[2]);

      // Handle HTTP/1.1 100 Continue
      if ($obj->responseCode==100) {
        return $this->interpretResponse($body);
      }
    }

    return $obj;
  }

  /**
   * Stolen from OAuth_common
   */
  public static function urlencode_rfc3986($input) {
    if (is_array($input)) {
      return array_map(array('RestClient', 'urlencode_rfc3986'), $input);
    } else if (is_scalar($input)) {
      return str_replace(
        '+',
        ' ',
        str_replace('%7E', '~', rawurlencode($input))
      );
    } else {
      return '';
    }
  }

  /**
   * Check for curl error
   * Returns FALSE if no error occured
   */
  public function getCurlError() {
    if (empty($this->lastError)) {
      $this->lastError = FALSE;
    }
    return $this->lastError;
  }

}

