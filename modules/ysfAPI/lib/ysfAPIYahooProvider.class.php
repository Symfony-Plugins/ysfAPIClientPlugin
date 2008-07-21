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
 * ysfAPIYahooProvider is a provider for Yahoo! BOSS.
 *
 * @package    ysymfony
 * @subpackage api
 * @author     Dustin Whittle <dustin@symfony-project.com>
 */
class ysfAPIYahooProvider extends ysfAPIBaseProvider
{

  private $appid = null;

  public static function getServer()
  {
    return 'yahoo';
  }

  public function buildSearchRequest($id, $parameters, $options)
  {
    if(empty($parameters['query']))
    {
      throw new ysfAPIClientException('The query parameter is required for yahoo.search request.');
    }

    return $this->buildCurlRequest('/'.urlencode($parameters['query']));
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
    $url = trim($this->host.':'.$this->port.$this->prefix.$this->version.$uri.'?'.http_build_query(array_merge(array('format' => 'json', 'count' => 20, 'appid' => $this->parameters['appid']), $parameters), null, '&'));

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
