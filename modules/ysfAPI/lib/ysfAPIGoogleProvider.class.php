<?php

/**
 * Copyright (c) 2008 Yahoo! Inc.  All rights reserved.
 *
 * The copyrights embodied in the content in this file are licensed under
 * the MIT open source license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * ysfAPIGoogleProvider is a provider for Google search json api.
 *
 * @package    ysymfony
 * @subpackage api
 * @author     Dustin Whittle <dustin@symfony-project.com>
 */
class ysfAPIGoogleProvider extends ysfAPIBaseProvider
{

  private $appid = null;

  public static function getServer()
  {
    return 'google';
  }

  public function buildSearchRequest($id, $parameters, $options)
  {
    if(empty($parameters['query']))
    {
      throw new ysfAPIClientException('The query parameter is required for google.search request.');
    }

    return $this->buildCurlRequest('', array('v' => '1.0', 'q' => $parameters['query'], 'rsz' => 'large'));
  }

  /**
   * buildCurlRequest generates a request to insert into the stack
   *
   * @param array $uri uri
   * @param array $parameters to send as part of the request
   * @param array $options to specify to the api or curl
   *
   * @return string request to insert into the stack and execute
   */
  public function buildCurlRequest($uri, $parameters = array(), $options = array())
  {
    $url = trim($this->host.':'.$this->port.$this->prefix.$this->version.$uri);

    if(!empty($parameters))
    {
     $url = $url.'?'.http_build_query($parameters, null, '&');
    }

    $options = sfToolkit::arrayDeepMerge($this->options, sfToolkit::arrayDeepMerge($options, array(CURLOPT_URL => $url, CURLOPT_TIMEOUT => $this->timeout)));

    $ch = curl_init();
    curl_setopt_array($ch, $options);

    if($ch === false)
    {
      throw new ysfAPIClientException(sprintf('The curl request could not be built for %s with parameters %s', $uri, var_export($parameters, true)));
    }

    return $ch;
  }

}
