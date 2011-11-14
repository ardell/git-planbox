<?php

class GitPlanbox_List extends CLIMax_BaseCommand
{

  public function run($arguments, CLImaxController $cliController)
  {
    // Create a session so we can run commands
    $session = GitPlanbox_Session::create();

    // Get a list of stories
    $config   = GitPlanbox_Config::get();
    $postData = array('product_id' => $config->productid());
    $stories  = $session->post('get_stories', $postData);

    // Format stories nicely
    foreach ($stories as $story)
    {
      // Skip stories that are done
      $statusesToSkip = array('completed', 'delivered', 'accepted', 'rejected', 'released', 'blocked');
      if (in_array($story->status, $statusesToSkip)) continue;

      printf("%8s %9s %10s - %-50s\n", "#{$story->id}", $story->type, $story->status, $story->name);
    }

    return 0;
  }

  public function getDescription($aliases, $argLinker) {
    return 'List planbox stories.';
  }

}
