<?php

/**
 *
 * Copyright (c) 2008 Yahoo! Inc.  All rights reserved.
 * The copyrights embodied in the content in this file are licensed
 * under the MIT open source license.
 *
 * For the full copyright and license information, please view the LICENSE.yahoo
 * file that was distributed with this source code.
 *
 */

/**
 * ysfAPIClient - A flexible api client for fetching data from rest based web services or custom compound web services using json or xml.
 *
 * @package    ysymfony
 * @subpackage api
 * @author     Dustin Whittle <dustin.whittle@symfony-project.com>
 */
class ysfAPIClient
{

  protected static $instance = null;

  protected $context = null;

  private $cache = null,
          $batches = array(),
          $requests = array(),
          $responses = array(),
          $options = array(),
          $componentData = array();

  /**
   * Retrieve the singleton instance of this class.
   *
   * @return ysfAPIClient A ysfAPIClient implementation instance.
   */
  public static function getInstance()
  {
    if(!isset(self::$instance))
    {
      $class = __CLASS__;
      self::$instance = new $class(sfContext::getInstance());
    }

    return self::$instance;
  }

  /**
   * Checks for an available instance
   *
   * @return boolean
   */
  public static function hasInstance()
  {
    return isset(self::$instance);
  }

  /**
   * Class constructor.
   *
   * @see initialize()
   */
  public function __construct(sfContext $context, $options = array())
  {
    $this->initialize($context, $options);
  }

  /**
   * Initializes this instance.
   *
   * @param sfContext $context  An sfContext instance
   * @param array             $options     An associative array of initialization options.
   */
  public function initialize(sfContext $context, $options = array())
  {
    $this->context = $context;
    $this->options = $options;

    $this->options['debug'] = isset($this->options['debug']) ? (boolean) $this->options['debug'] : false;
    $this->options['servers'] = isset($this->options['servers']) ? $this->options['servers'] : array();

    $this->loadConfiguration();
  }

  /**
   * Loads api configuration.
   *
   */
  public function loadConfiguration()
  {
    if($config = $this->context->getConfigCache()->checkConfig('config/api.yml', true))
    {
      require($config);
    }

    if(sfConfig::has('ysf_api_cache'))
    {
      $cacheAdapter = sfConfig::get('ysf_api_cache');
      $this->cache = new $cacheAdapter['class']($cacheAdapter['param']);
    }
    else
    {
      $this->cache = new sfNoCache();
    }

    $this->options['servers'] = sfToolkit::arrayDeepMerge($this->options['servers'], sfConfig::get('ysf_api_servers', array()));
  }

  /**
   * Sets options for the api client.
   *
   * @param mixed $options
   */
  public function setOptions($options)
  {
    $options['debug'] = isset($options['debug']) ? (boolean) $options['debug'] : $this->options['debug'];

    $this->options = $options;
  }

  /**
   * Gets options for the api client.
   *
   * @return unknown
   */
  public function getOptions()
  {
    return $this->options;
  }

  /**
   * Sets cache instance for api client.
   *
   * @param sfCache $cache A sfCache instance
   */
  public function setCache(sfCache $cache)
  {
    $this->cache = $cache;
  }

  /**
   * Returns cache instance for api client.
   */
  public function getCache()
  {
    return $this->cache;
  }

  /**
   * Executes requests and fetches data.
   *
   * @return boolean True if all responses are successful, otherwise false
   */
  public function execute()
  {
    if(is_array($this->options['servers']) && !empty($this->options['servers']))
    {
      foreach($this->options['servers'] as $server => $options)
      {
        $providerClass = 'ysfAPI' . sfInflector::camelize($server) . 'Provider';
        if(isset($this->requests[$server]) && is_array($this->requests[$server]) && !empty($this->requests[$server]) && $this->providerExists($providerClass, 'buildCurlRequest'))
        {
          $provider = new $providerClass($options);

          $options['method'] = isset($options['method']) ? $options['method'] : 'rest';

          $options[$options['method']]['uri'] = isset($options[$options['method']]['uri']) ? $options[$options['method']]['uri'] : '';

          $options[$options['method']]['parameter'] = isset($options[$options['method']]['parameter']) ? $options[$options['method']]['parameter'] : '';

          if(isset($options['method']) && $options['method'] == 'compound')
          {
            $id = md5($server . 'compound');
            $request = $this->encodeCompound($this->requests[$server], $this->options['servers'][$server]);

            if(!empty($request))
            {
              $encodedRequest = $this->encodeRequest($request, $options['format']);

              $this->batches[$id]['request'] = $encodedRequest;

              $curlRequest = $provider->buildCurlRequest($options[$options['method']]['uri'], array($options[$options['method']]['parameter'] => $encodedRequest));

              if($curlRequest !== false)
              {
                $this->batches[$id]['handle'] = $curlRequest;
              }
            }
          }
          else // single requests (usually rest or similar)
          {
            foreach($this->requests[$server] as $id => $request)
            {
              if(!isset($this->responses[$id]) && ($request != false))
              {
                $this->batches[$id]['handle'] = $request;
              }
            }
          }
        }
      }
    }

    if(!empty($this->batches))
    {
      $mh = curl_multi_init();

      foreach($this->batches as $id => $batch)
      {
        if($this->options['debug'])
        {
          $this->context->getLogger()->info(sprintf("executing batch id '%s'", $id), sfLogger::DEBUG);
        }

        curl_multi_add_handle($mh, $batch['handle']);
      }

      do
      {
        $mrc = curl_multi_exec($mh, $active);
      }
      while($mrc == CURLM_CALL_MULTI_PERFORM);

      while(($active and $mrc == CURLM_OK))
      {
        if(curl_multi_select($mh) != -1)
        {
          do
          {
            $mrc = curl_multi_exec($mh, $active);
          }
          while($mrc == CURLM_CALL_MULTI_PERFORM);
        }
      }

      $errors = curl_multi_info_read($mh);
      if($errors['result'] != CURLM_OK)
      {
        // check each payload for any errors and verify integrity of response
        // catch curl errors (timeouts or dns)
        $this->context->getLogger()->err(sprintf("{ysfAPIClient} API error: (curl #%s) '%s'", curl_errno($errors['handle']), curl_error($errors['handle'])));
      }

      $still_running = 0;
      do
      {
        curl_multi_info_read($mh, $still_running);
      }
      while($still_running);

      foreach($this->options['servers'] as $server => $options)
      {
        if(isset($options['method']) && ($options['method'] == 'compound'))
        {
          if(isset($this->batches[$id]))
          {
            $this->responses[$id] = array();
            $this->responses[$id]['info'] = curl_getinfo($this->batches[$id]['handle']);
            $this->responses[$id]['data'] = curl_multi_getcontent($this->batches[$id]['handle']);

            $decodedResponse = $this->decodeResponse($this->responses[$id]['data'], $options['format']);

            if($this->checkResponse($decodedResponse))
            {
              $this->decodeCompound($decodedResponse);
            }
            else
            {
              if($this->options['debug'])
              {
                $this->context->getLogger()->err(sprintf("{ysfAPIClient} checking response for batch id '%s' failed", $id));
              }
            }
          }
        }
        elseif(!isset($this->responses[$id]))
        {
          foreach($this->batches as $id => $batch)
          {

            $response = $this->decodeResponse(curl_multi_getcontent($batch['handle']), $options['format']);
            if($this->checkResponse($response))
            {
              $this->responses[$id] = array();
              $this->responses[$id]['info'] = curl_getinfo($batch['handle']);
              $this->responses[$id]['data'] = $response;
            }
            else
            {
              if($this->options['debug'])
              {
                $this->context->getLogger()->err(sprintf("{ysfAPIClient} checking response for batch id '%s' failed", $compoundBatchId));
              }
            }
          }
        }

        $this->requests[$server] = array();
      }

      $this->batches = array();

      return true;
    }
    else
    {
      return false;
    }
  }

  /**
   * Add a request.
   *
   * @param string $call The api call in server.provider
   * @param array $parameters The parameters for the call
   * @param array $options The curl options for the request
   *
   * @return True if successful, otherwise false.
   */
  public function addRequest($call, $parameters = array(), $options = array())
  {
    if(strpos($call, '.'))
    {
      list($class, $method) = explode('.', $call, 2);
    }
    else
    {
      throw new ysfAPIClientException(sprintf("The call '%s' is not valid, please refer to the specification.", $call));
    }

    $className = 'ysfAPI' . sfInflector::camelize($class) . 'Provider';
    $methodName = 'build' . sfInflector::camelize($method) . 'Request';
    if($this->providerExists($className, $methodName))
    {
      $server = call_user_func(array($className, 'getServer'));

      if(isset($this->options['servers'][$server]))
      {
        $serverParameters = $this->options['servers'][$server];

        $id = md5($server . $call . serialize($options));
        if(!isset($this->requests[$server][$id]))
        {
          $$className = new $className($serverParameters);
          $request = $$className->$methodName($id, array_merge($serverParameters, $parameters), $options);
          if(false !== $request)
          {
            $this->requests[$server][$id] = $request;

            if($this->options['debug'])
            {
              $this->context->getLogger()->info(sprintf("{ysfAPIClient} adding request '%s' with parameters %s", $call, str_replace(array('array (', '0 => ', ",\n  )", ",\n)"), '', var_export($options, true))));
              $this->context->getLogger()->info(sprintf("{ysfAPIClient} adding request id '%s' for server '%s'", $id, $server));
            }
          }
          else
          {
            throw new ysfAPIClientException(sprintf("The provider method '%s->%s()' could not produce a request", $className, $methodName));
          }
          return $id;
        }
        else
        {
          return $id;
        }
      }
      else
      {
        throw new ysfAPIClientException(sprintf('Try to add request for non-existent server: %s', $server));
      }
    }
  }

  /**
   * Get a request from the stack.
   *
   * @param string $server The server to get request from
   * @param string $id The id of the request
   *
   * @return mixed The data + statistics of the request
   */
  public function getRequest($server, $id)
  {
    if(isset($this->requests[$server][$id]))
    {
      return $this->requests[$server][$id];
    }
    else
    {
      return false;
    }
  }

  /**
   * Returns the requests.
   *
   * @return array The requests to be processed
   */
  public function getRequests()
  {
    return $this->requests;
  }

  /**
   * Adds a response to a request.
   *
   * @param string $id The id of the request
   * @param string $response The response
   *
   * @return boolean True if successful, otherwise false
   */
  public function addResponse($id, $response)
  {
    $this->responses[$id] = $response;
    return true;
  }

  /**
   * Returns the response.
   *
   * @param string $id The request id
   *
   * @return mixed The response
   */
  public function getResponse($id)
  {
    if(isset($this->responses[$id]))
    {
      return $this->responses[$id];
    }
    else
    {
      return false;
    }
  }

  /**
   * Returns the responses for all requests.
   *
   * @return array The array of responses
   */
  public function getResponses()
  {
    return $this->responses;
  }

  /**
   * Returns data for a request id.
   *
   * @param string $id The request id
   *
   * @return mixed The data for the response
   */
  public function getData($id)
  {
    if(isset($this->responses[$id]) && !empty($this->responses[$id]['data']))
    {
      return $this->responses[$id]['data'];
    }
    else
    {
      return false;
    }
  }

  /**
   * Return statistics for a response.
   *
   * @param string $id
   *
   * @return mixed The stats for a response
   *
   * "url"
   * "content_type"
   * "http_code"
   * "header_size"
   * "request_size"
   * "filetime"
   * "ssl_verify_result"
   * "redirect_count"
   * "total_time"
   * "namelookup_time"
   * "connect_time"
   * "pretransfer_time"
   * "size_upload"
   * "size_download"
   * "speed_download"
   * "speed_upload"
   * "download_content_length"
   * "upload_content_length"
   * "starttransfer_time"
   * "redirect_time"
   *
   *
   */
  public function getStatistics($id)
  {
    if(isset($this->responses[$id]) && !empty($this->responses[$id]['info']))
    {
      return $this->responses[$id]['info'];
    }
    else
    {
      return false;
    }
  }

  /**
   * Checks the request validity.
   *
   * @param mixed $request
   *
   * @return boolean
   */
  public function checkRequest($request)
  {
    return true;
  }

  /**
   * Checks the response validity.
   *
   * @param mixed $response
   *
   * @return boolean
   */
  public function checkResponse($response)
  {
    return true;
  }

  /**
   * Encodes the request given the format.
   *
   * @param string $request
   * @param string $format
   *
   * @return mixed
   */
  public function encodeRequest($request, $format = 'json')
  {
    $encodeMethod = 'encode' . ucfirst($format);
    if(method_exists(__CLASS__, $encodeMethod))
    {
      $requestEncoded = $this->$encodeMethod($request);

      return $requestEncoded;
    }
    else
    {
      if($this->options['debug'])
      {
        $this->context->getLogger()->err('{ysfAPIClient} Invalid request encoding format specified: ' . $format);
      }
      return false;
    }
  }


  /**
   * Decode the response given the format.
   *
   * @param string $response
   * @param string $format
   *
   * @return mixed
   */
  public function decodeResponse($response, $format = 'json')
  {

    $decodeMethod = 'decode' . ucfirst($format);
    if(method_exists(__CLASS__, $decodeMethod))
    {
      $responseDecoded = $this->$decodeMethod($response);
      return $responseDecoded;
    }
    else
    {
      if($this->options['debug'])
      {
        $this->context->getLogger()->err('{ysfAPIClient} Invalid response decoding format specified: ' . $format);
      }
      return false;
    }
  }

  /**
   * Encodes data to json format.
   *
   * @param mixed $data
   *
   * @return mixed The encoded json
   */
  public function encodeJson($data)
  {
    if(function_exists('json_encode'))
    {
      return json_encode($data);
    }
    elseif(class_exists('Zend_Json', true) && method_exists('Zend_Json', 'encode'))
    {
      return Zend_Json::encode($data);
    }
    elseif(class_exists('Services_JSON', true) && method_exists('Services_JSON', 'encode'))
    {
      $json = new Services_JSON();
      return $json->encode($data);
    }
    else
    {
      throw new ysfAPIClientException('JSON support not found in PHP');
    }
  }

  /**
   * Decodes data to json format.
   *
   * @param mixed $data
   *
   * @return mixed The decoded json
   */
  public function decodeJson($data)
  {
    if(function_exists('json_decode'))
    {
      return json_decode($data);
    }
    elseif(class_exists('Zend_Json', true) && method_exists('Zend_Json', 'encode'))
    {
      return Zend_Json::decode($data);
    }
    elseif(class_exists('Services_JSON', true) && method_exists('Services_JSON', 'decode'))
    {
      $json = new Services_JSON();
      return $json->decode($data);
    }
    else
    {
      throw new ysfAPIClientException('JSON support not found in PHP');
    }
  }

  /**
   * Encodes data to xml format.
   *
   * @param mixed $data
   *
   * @return mixed The encoded xml
   */
  public function encodeXml($data)
  {
    return $data;
  }

  /**
   * Decodes data to xml format.
   *
   * @param mixed $data
   *
   * @return mixed The encoded xml
   */
  public function decodeXml($data)
  {
    return simplexml_load_string($data);
  }

  /**
   * Encodes compounds (multiple requests) for a server.
   *
   * @param array $requests
   * @param string $server
   *
   * @return mixed
   */
  public function encodeCompound($requests, $server)
  {
    return $requests;
  }

  /**
   * Decodes compound from a response.
   *
   * @param array $compound
   *
   * @return mixed
   */
  public function decodeCompound($compound)
  {
    return $compound;
  }

  /**
   * Adds data for a module and component.
   *
   * @param string $moduleName
   * @param string $componentName
   * @param string $variableName
   * @param string $variableValue
   */
  public function addComponentData($moduleName, $componentName, $variableName, $variableValue)
  {
    $this->componentData[$moduleName . '/' . $componentName][$variableName] = $variableValue;
  }

  /**
   * Returns all component data.
   *
   * @param string $moduleName
   * @param string $componentName
   *
   * @return mixed
   */
  public function getAllComponentData($moduleName, $componentName)
  {
    if(isset($this->componentData[$moduleName . '/' . $componentName]))
    {
      $data = array();
      foreach($this->componentData[$moduleName . '/' . $componentName] as $variableName => $variableValue)
      {
        if(is_string($variableValue) && isset($this->responses[$variableValue]))
        {
          $data[$variableName] = $this->getData($variableValue);
        }
        else
        {
          $data[$variableName] = $variableValue;
        }
      }
      return $data;
    }
    else
    {
      return false;
    }
  }

  /**
   * Checks if a given provider is valid.
   *
   * @param string $class
   * @param string $method
   *
   * @return mixed
   */
  private function providerExists($class, $method)
  {
    if(class_exists($class, true) && method_exists($class, $method))
    {
      return true;
    }
    else
    {
      throw new ysfAPIClientException(sprintf('The provider method "%s->%s()" does not exist.', $class, $method));
    }
  }

}
