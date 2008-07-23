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
  public function executeSearch($request)
  {

    // search form
    $this->form = new sfForm();
    $this->form->setWidgets(array('query' => new sfWidgetFormInput(array(), array('class' => 'search-box'))));
    $this->form->setValidators(array('query' => new sfValidatorString(array('min_length' => 3))));
    $this->form->getWidgetSchema()->setNameFormat('search[%s]');
    $this->form->getWidgetSchema()->setFormFormatterName('list');

    // search results
    $this->results = array();

    if($request->isMethod('post'))
    {
      // bind posted form
      $this->form->bind($request->getParameter('search'));

      if($this->form->isValid())
      {
        $this->query = $this->form->getValue('query');

        $api = ysfAPIClient::getInstance();

        // parallel
        $ysearch = $api->addRequest('yahoo.search', array('query' => $this->query), array(CURLOPT_USERAGENT => 'my Y! search'));
        $gsearch = $api->addRequest('google.search', array('query' => $this->query), array(CURLOPT_USERAGENT => 'my G search'));

        if($api->execute())
        {
          $yJson = $api->getData($ysearch);
          $gJson = $api->getData($gsearch);

          if(isset($yJson->ysearchresponse) && isset($gJson->responseData))
          {
            // normalization logic could be moved to each provider
            foreach (array_merge($yJson->ysearchresponse->resultset_web, $gJson->responseData->results) as $data)
            {
              $result = new stdClass();
              $result->title = $data->title;
              $result->abstract = isset($data->abstract) ? $data->abstract : $data->content;
              $result->url = $data->url;

              array_push($this->results, $result);
            }
          }
        }
      }

    }

    return sfView::SUCCESS;
  }

}