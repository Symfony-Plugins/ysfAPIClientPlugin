<?php

/**
 * ysfWebDebugPanelAPIClient adds a panel to the web debug toolbar with timer information.
 *
 * @package    ysfAPIClientPlugin
 * @subpackage debug
 * @author     Dustin Whittle <dustin.whittle@symfony-project.com>
 * @version    SVN: $Id: ysfWebDebugPanelAPIClient.class.php 12982 2009-2-13 17:25:10Z dwhittle $
 */
class ysfWebDebugPanelAPIClient extends sfWebDebugPanel
{
  
  private static $startTime = null;
  private $logs = array();

  /**
   * Constructor.
   *
   * @param sfWebDebug $webDebug The web debut toolbar instance
   */
  public function __construct(sfWebDebug $webDebug)
  {
    parent::__construct($webDebug);

    $this->webDebug->getEventDispatcher()->connect('debug.web.filter_logs', array($this, 'filterLogs'));
  }

  public function getTitle()
  {
    return '<img src="'.$this->webDebug->getOption('image_root_path').'/time.png" alt="Time" /> '.$this->getTotalTime().' ms';
  }

  public function getPanelTitle()
  {
    return 'API';
  }

  public function getPanelContent()
  {
    // $this->context->getLogger()->info(sprintf("{ysfAPIClient} Executing batch #%s with %s requests", ++self::$count, count($this->batches)), sfLogger::DEBUG);

    $panel = '<div><ul>';
    foreach($this->logs as $log)
    {
      $panel .= '<li>' . $log['message'] . '</li>';
    }
    $panel .= '</ul></div>';

    return $panel;
  }

  public function filterLogs(sfEvent $event, $logs)
  {
    $newLogs = array();
    foreach ($logs as $log)
    {
      if ('ysfAPIClient' == $log['type'])
      {
        $this->logs[] = $log;
      }
      else
      {
        $newLogs[] = $log;
      }
    }

    return $newLogs;
  }

  protected function getTotalTime()
  {
    // more accurate
    $time = 0;
    $timers = sfTimerManager::getTimers();
    if(isset($timers['API Requests']) && $timers['API Requests'] instanceof sfTimer)
    {
      $time = $timers['API Requests']->getElapsedTime();
    }
    else
    {
      // aggregate time of all responses (individual request times, not batch times)
      $responses = ysfAPIClient::getInstance()->getResponses();
      foreach ($responses as $id => $response)
      {
        $time += $response['info']['total_time'];
      } 
    }
    
    return !is_null($time) ? sprintf('%.0f', ($time * 1000)) : 0;
  }
  
  public static function listenToAddPanelEvent(sfEvent $event)
  {
    $event->getSubject()->setPanel('API', new self($event->getSubject()));
  }

}
