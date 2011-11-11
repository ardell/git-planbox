<?php

class GitPlanbox_Start extends CLIMax_BaseCommand
{

  public function run($arguments, CLImaxController $cliController)
  {
    // Require a story id
    if (!isset($arguments[0]))
    {
      throw new Exception("Please specify a story_id to start.");
    }
    $storyId = $arguments[0];

    // Get the story from Planbox
    $session = GitPlanbox_Session::create();
    try {
      $story   = $session->post('get_story', array('story_id' => $storyId));
    } catch (GitPlanbox_ApplicationErrorException $e) {
      throw new Exception("Unable to fetch story {$storyId} from Planbox.");
    }

    // Confirm that they want to branch off the current branch
    $currentBranch = GitPlanbox_Util::currentGitBranchName();
    print("Starting story: {$story->name}\n");
    print("  (branching off {$currentBranch})\n");

    // Ask the user what they'd like to call the branch
    $config = GitPlanbox_Config::get();
    $branch = NULL;
    while(!$branch)
    {
      $branch = GitPlanbox_Util::readline("What should we name the branch? ");
      if (!preg_match($config->branchregex(), $branch))
      {
        print("Error: Branch names must match regex: '{$config->branchregex()}'.\n");
        $branch = NULL;
      }
    }

    // Build the branch name based on the branch-name-template in config
    $branchName = $this->_buildBranchName($branch, $storyId, $currentBranch);

    // Create the branch
    $command = "git checkout -b {$branchName} {$currentBranch}";
    exec($command, $output, $returnCode);
    if ($returnCode !== 0) throw new Exception("Error creating new branch.");

    // @TODO later...
    //    If there is only one task, start the timer for that task
    //    Otherwise aask which task they want to start
    //    Tell the user that the task's timer has been started

    // Success!
    return 0;
  }

  private function _buildBranchName($branchName, $storyId, $parent)
  {
    $config         = GitPlanbox_Config::get();
    $branchTemplate = $config->branchtemplate();

    // Substitute variables
    $substitutions = array(
      'author'  => $config->author(),
      'name'    => $branchName,
      'parent'  => $parent,
      'storyid' => $storyId,
    );
    foreach ($substitutions as $key => $value)
    {
      $branchTemplate = str_replace("#{$key}#", $value, $branchTemplate);
    }

    return $branchTemplate;
  }

}
