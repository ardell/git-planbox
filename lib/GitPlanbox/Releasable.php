<?php

/**
 * git pb releasable [iteration]
 *
 * List stories that are completed, delivered, or accepted
 * but not yet released.
 */
class GitPlanbox_Releasable extends CLIMax_BaseCommand
{

  public function run($arguments, CLImaxController $cliController)
  {
    // Set up options for search
    $config   = GitPlanbox_Config::get();
    $statuses = array('completed', 'delivered', 'accepted');
    $opts     = array(
      'productid' => $config->productid(),
      'timeframe' => $timeframe,
      'status'    => $statuses,
    );

    // Pull in timeframe(s) if they've been specified
    if (isset($arguments[0]))
    {
      $opts['timeframe'] = explode(',', $arguments[0]);
    } else {
      $opts['timeframe'] = array('last', 'current', 'next');
    }

    // Create a session so we can run commands
    $session = GitPlanbox_Session::create();

    // Get a list of stories
    $stories = GitPlanbox_Search::search($session, $opts);

    if (count($stories) == 0)
    {
      print("There are no stories in status: " . implode(', ', $statuses) . ".\n");
      return 0;
    }

    // Format stories nicely
    print("Stories that are ready for release:\n");
    foreach ($stories as $story)
    {
      printf("%8s %10s - %-50s\n", "#{$story->id}", $story->status, $story->name);
    }
    print("\nHint: use `git-planbox status <storyId>` to find out what branch the story is on.\n\n");

    return 0;
  }

  public function getDescription($aliases, $argLinker)
  {
    return 'List planbox stories that are ready for release.';
  }

}
