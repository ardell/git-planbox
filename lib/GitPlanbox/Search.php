<?php

class GitPlanbox_Search
{

  /**
   * Find planbox stories matching a set of criteria.
   *
   * @param array $opts Options for restricting the search:
   *   - productid: (int) The planbox product id (project identifier)
   *   - timeframe: (mixed) String or array of timeframes, e.g. array('current', 'next', 'backlog') or 'current'
   *   - resourceid: (mixed) Int or array of resourceids of planbox users involved in stories
   *   - status: (mixed) String or array of statuses to include, e.g. array('completed', 'delivered') or 'completed' 
   */
  public static function search($session, $opts = array())
  {
    if (!$session) throw new Exception("Invalid GitPlanbox_Session.");

    $postData = array();

    // Restrict by product id
    if (isset($opts['productid'])) $postData['product_id'] = $opts['productid'];

    // Restrict by timeframe(s), default=current
    if (isset($opts['timeframe']))
    {
      $postData['timeframe'] = $opts['timeframe'];
    } else {
      $postData['timeframe'] = 'current';
    }

    // Restrict by user (i.e. the git-planbox user)
    if (isset($opts['resourceid'])) $postData['resource_id'] = $opts['resourceid'];

    // Get the list of stories
    $stories = $session->post('get_stories', $postData);

    // Restrict by story status(es)
    if (!isset($opts['status'])) return $stories;
    $statuses = $opts['status'];
    if (!is_array($opts['status'])) $statuses = array($statuses);
    $retVal = array();
    foreach ($stories as $story)
    {
      // Skip stories with statuses we don't care about
      if (!in_array($story->status, $statuses)) continue;
      array_push($retVal, $story);
    }

    // Return the list
    return $retVal;
  }

}

