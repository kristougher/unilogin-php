<?php

namespace Unilogin\Unilogin;

class UniloginClient
{
  // Define the necessary web service URL´s.
  const WS02 = 'https://ws02.infotjeneste.uni-c.dk/infotjeneste-ws/ws';
  const WS03 = 'https://ws03.infotjeneste.uni-c.dk/infotjenestelicens-ws/ws?WSDL';
  const WS05 = 'https://ws05.infotjeneste.uni-c.dk/infotjenesteautorisation-ws/ws?WSDL';
  const REQ_BODY = '<x:Envelope xmlns:x="http://schemas.xmlsoap.org/soap/envelope/" xmlns:inf="https://infotjeneste.uni-c.dk"><x:Header/><x:Body>%s</x:Body></x:Envelope>';
  const USERPARAM = 'wsBrugerid';
  const PWPARAM = 'wsPassword';

  protected $requestParams = [];
  protected $requestBody = '';

  public function __construct($wsUser, $wsPw) {
    $this->addParam(UniloginClient::USERPARAM, $wsUser);
    $this->addParam(UniloginClient::PWPARAM, $wsPw);
  }

  /**
   * Add a parameter for the request.
   */
  public function addParam($param, $value) {
    $this->requestParams[$param] = $value;
  }

  /**
   * Add an array of parameters to the request.
   *
   * @param array $params
   *   An associative array keyed by the param name.
   */
  public function addParams($params) {
    $this->requestParams = array_merge($this->requestParams, $params);
  }

  /**
   * Flush the stored params to reuse the object fort another request.
   */
  public function flushParams($keepCredentials = TRUE) {
    if (!$keepCredentials) {
      $this->requestParams = [];
      return;
    }
    $temp = $this->requestParams;
    $this->requestParams = [
      UniloginClient::USERPARAM => $temp[UniloginClient::USERPARAM],
      UniloginClient::PWPARAM => $temp[UniloginClient::PWPARAM],
    ];
  }

  /**
   * Make a call to an Infotjeneste webservice.
   *
   * @TODO: Make non-drupal request.
   */
  public function callWSDL($method, $params, $ws = NULL) {
    $this->addParams($params);
    $this->renderRequestBody($method);
    $responseKeys = unilogin_response_params($method);

    $url = $this->getURL($method);

    $result = drupal_http_request($url, array(
      'headers' => $this->getHeaders($method),
      'method' => 'POST',
      'data' => $this->requestBody,
    ));

    if (empty($result->data)) {
      return [];
    }
    $interpreted = $this->interpretXML($result->data, $responseKeys['tag'], $responseKeys['params']);

    return $interpreted;
  }

  /**
   * Render a single parameter for the request XML.
   */
  protected function renderParam($param, $value) {
    return sprintf('<inf:%s>%s</inf:%s>', $param, $value, $param);
  }

  /**
   * Render the XML request body and store it in $requestBody.
   *
   * The body is returned as well.
   */
  protected function renderRequestBody($method) {
    $body = '';
    foreach ($this->requestParams as $param => $value) {
      $body .= $this->renderParam($param, $value);
    }
    $body = $this->renderParam($method, $body);
    $this->requestBody = sprintf(UniloginClient::REQ_BODY, $body);
    return $this->requestBody;
  }

  /**
   * Return the WS values as an associative array.
   */
  public function interpretXML($xml, $responseKey, $responseValues) {
    $doc = new \DOMDocument();
    $doc->loadXML($xml);
    $values = [];
    $result = $doc->getElementsByTagName($responseKey);

    // For really simple responses, return the content of the node
    if (empty($responseValues)) {
      return [$result->item(0)->nodeValue];
    }

    foreach ($result as $node) {
      foreach ($responseValues as $nodeName) {
        $values[$nodeName] = $node->getElementsByTagName($nodeName)->item(0)->nodeValue;
      }
    }
    return $values;
  }

  /**
   * Most of the calls are to WS02 return WS05 on a specific method.
   */
  protected function getURL ($method) {
    if ($method == 'harBrugerLicens') {
      return UniloginClient::WS05;
    }
    return UniloginClient::WS02;
  }

  /**
   * Simple wrapper for the headers required for the SOAP call.
   */
  protected function getHeaders ($method) {
    $headers = array(
      'SOAPAction' => $method,
      'Content-Type' => 'text/xml; charset="utf-8"',
      'Content-Length' => strlen($this->requestBody),
      'Accept' => 'text/xml',
      'Cache-Control' => 'no-cache',
      'Pragma' => 'no-cache',
      'max_redirects' => 5,
    );

    return $headers;
  }
}
