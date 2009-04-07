<?php

/**
 * ysfAPIClientPlugin configuration.
 * 
 * @package     ysfAPIClientPlugin
 * @subpackage  config
 * @author      Dustin Whittle <dustin.whittle@symfony-project.com>
 * @version     SVN: $Id: ysfAPIClientPluginConfiguration.class.php 12956 2008-11-12 17:35:45Z dwhittle $
 */
class ysfAPIClientPluginConfiguration extends sfPluginConfiguration
{
  /**
   * @see sfPluginConfiguration
   */
  public function initialize()
  {
    if (sfConfig::get('sf_debug'))
    {
      require_once(dirname(__FILE__).'/../lib/debug/ysfWebDebugPanelAPIClient.class.php');

      $this->dispatcher->connect('debug.web.load_panels', array('ysfWebDebugPanelAPIClient', 'listenToAddPanelEvent'));
    }
  }
}
