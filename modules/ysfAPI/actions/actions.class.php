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
 * ysfAPI actions.
 *
 * @package    ysymfony
 * @subpackage api
 * @author     Dustin Whittle <dustin@symfony-project.com>
 * @version    SVN: $Id: actions.class.php 2692 2006-11-15 21:03:55Z fabien $
 */
class ysfAPIActions extends sfActions
{
  /**
   * Executes index action
   *
   */
  public function executeIndex($request)
  {

    $timer = sfTimerManager::getTimer('API Requests');

    $api = ysfAPIClient::getInstance();

    $this->query = $request->getParameter('query', 'symfony');

    $ysearch = $api->addRequest('yahoo.search', array('query' => $this->query), array(CURLOPT_USERAGENT => 'my Y! search'));
    $gsearch = $api->addRequest('google.search', array('query' => $this->query), array(CURLOPT_USERAGENT => 'my G search'));

    if($api->execute())
    {
      $yJson = $api->getData($ysearch);
      $gJson = $api->getData($gsearch);

      // normalization logic can be moved to provier
      $this->results = array();
      foreach (array_merge($yJson->ysearchresponse->resultset_web, $gJson->responseData->results) as $data)
      {
        $result = new stdClass();
        $result->title = $data->title;
        $result->abstract = isset($data->abstract) ? $data->abstract : $data->content;
        $result->url = $data->url;

        array_push($this->results, $result);
      }
    }
    else
    {
      $this->results = array();
    }
    $timer->addTime();

    return sfView::SUCCESS;
  }

}