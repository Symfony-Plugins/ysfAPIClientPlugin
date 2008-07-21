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
 * ysfAPIBaseProvider - A base provider for all api client providers.
 *
 * @package    ysymfony
 * @subpackage api
 * @author     Dustin Whittle <dustin.whittle@symfony-project.com>
 */
abstract class ysfAPIBaseProvider
{

  public  $parameters = array(),
          $options = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false),
          $format = null,
          $host = null,
          $port = 80,
          $prefix = null,
          $version = null,
          $timeout = 10;

  /**
   * Class constructor.
   *
   * @see initialize()
   */
  public function __construct($parameters = array(), $options = array())
  {
    $this->initialize($parameters, $options);
  }

  public function initialize($parameters = array(), $options = array())
  {
    if(!empty($parameters))
    {
      $definedVars = get_class_vars(__CLASS__);
      foreach ($parameters as $key => $value)
      {
        if(array_key_exists($key, $definedVars))
        {
          $this->{$key} = $value;

          unset($parameters[$key]);
        }
        else
        {
          $this->parameters[$key] = $value;
        }
      }
    }
  }

  /**
   * buildCurlRequest generates a request to insert into the stack
   *
   * @param array $uri uri
   * @param array $parameters to send as part of the request
   * @param array $options to specify to the api or curl
   * @return string request to insert into the stack and execute
   */
  public function buildCurlRequest($uri, $parameters = array(), $options = array())
  {
    $queryString = http_build_query($parameters);

    $url = trim($this->host.':'.$this->port.$this->prefix.$this->version.$uri);
    $options = sfToolkit::arrayDeepMerge($this->options, sfToolkit::arrayDeepMerge($options, array(CURLOPT_URL => $url.'?'.$queryString, CURLOPT_POSTFIELDS => $queryString, CURLOPT_TIMEOUT => $this->timeout)));

    $ch = curl_init();
    curl_setopt_array($ch, $options);

    if($ch === false)
    {
      throw new ysfAPIClientException(sprintf('The curl request could not be built for %s with parameters %s', $uri, var_export($parameters, true)));
    }

    return $ch;
  }

}
