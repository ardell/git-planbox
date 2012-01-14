<?php

class GitPlanbox_List extends CLIMax_BaseCommand
{

  public function run($arguments, CLImaxController $cliController)
  {
    // Pull in iterations if they've been specified, otherwise
    // use current as default
    $timeframe = 'current';
    if (isset($arguments[0])) $timeframe = explode(',', $arguments[0]);

    // Create a session so we can run commands
    $session = GitPlanbox_Session::create();

    // Get a list of stories
    $config   = GitPlanbox_Config::get();
    $postData = array(
      'product_id' => $config->productid(),
      'timeframe'  => $timeframe,
    );
    if ($config->resourceid()) $postData['resource_id'] = array($config->resourceid()); // Restrict list to the current user if they have specified a planbox.resourceid in gitconfig
    $stories  = $session->post('get_stories', $postData);

    // Format stories nicely
    $skippedStoriesByStatus = array();
    foreach ($stories as $story)
    {
      // Skip stories that are done
      $statusesToSkip = array('completed', 'delivered', 'accepted', 'rejected', 'released', 'blocked');
      if (in_array($story->status, $statusesToSkip))
      {
        if (!isset($skippedStoriesByStatus[$story->status])) $skippedStoriesByStatus[$story->status] = 0;
        $skippedStoriesByStatus[$story->status]++;
        continue;
      }

      printf("%8s %9s %10s - %-50s\n", "#{$story->id}", $story->type, $story->status, $story->name);
    }
    if (!empty($skippedStoriesByStatus))
    {
      $skippedStoryStatuses = array();
      foreach ($skippedStoriesByStatus as $status => $numSkipped)
      {
        array_push($skippedStoryStatuses, "{$numSkipped} {$status}");
      }
      print("...plus " . implode(', ', $skippedStoryStatuses) . " tasks that are not displayed.\n");
    }

    return 0;
  }

  public function getDescription($aliases, $argLinker) {
    return 'List planbox stories.';
  }

}
