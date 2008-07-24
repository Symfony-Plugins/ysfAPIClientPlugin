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
 * ysfExecutionFilter is the last filter registered for each filter chain. This
 * filter does all action and view execution.
 *
 * @package    ysymfony
 * @subpackage api
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Sean Kerr <skerr@mojavi.org>
 * @author     Dustin Whittle <dustin.whittle@symfony-project.com>
 * @version    SVN: $Id: sfExecutionFilter.class.php 4518 2007-07-03 05:47:15Z dwhittle $
 */
class ysfAPIExecutionFilter extends sfExecutionFilter
{
  /**
   * Executes the execute method of an action.
   *
   * @param  sfAction An sfAction instance
   *
   * @return string   The view type
   */
  protected function executeAction($actionInstance)
  {

    $api = sfConfig::get('ysf_api_enabled', false);

    if($api)
    {
      $controller = $this->getContext()->getController();
      $moduleName = $actionInstance->getModuleName();

      // prefetch configuration to determine components
      $this->getContext()->getConfigCache()->checkConfig(sfConfig::get('sf_app_module_dir_name').'/'.$moduleName.'/'.sfConfig::get('sf_app_module_config_dir_name').'/view.yml');

      // execute fetchDataFor method for each registered component
      require($this->getContext()->getConfigCache()->getCacheName('modules_'.$moduleName.'_config_components'));
    }

    // execute the action
    $actionInstance->preExecute();
    $viewName = $actionInstance->execute($this->getContext()->getRequest());
    $actionInstance->postExecute();

    if($api)
    {
      // fetch all api requests for modules
      ysfAPIClient::getInstance()->execute();
    }

    return $viewName ? $viewName : sfView::SUCCESS;
  }
}
