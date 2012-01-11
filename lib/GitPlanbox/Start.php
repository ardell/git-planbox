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

    // Start timer
    $this->_startTimer($session, $storyId);

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

  private function _startTimer($session, $storyId)
  {
    // Fetch the story
    $postData = array('story_id' => $storyId);
    $story  = $session->post('get_story', $postData);

    // Don't do anything if there are no tasks
    if (count($story->tasks) < 1)
    {
      print("Not starting timer because story {$storyId} has no tasks.");
      return;
    }

    // If we have only one task, start the timer for that task
    if (count($story->tasks) == 1)
    {
      $task = array_shift($story->tasks);
      $this->_startTimerForTask($session, $storyId, $task->id);
      return;
    }

    // Otherwise, ask the user which task they want to start
    $tasksByTaskId = array();
    foreach ($story->tasks as $task)
    {
      $tasksByTaskId[$task->id] = $task;
      printf("%8s %10s - %-50s\n", "#{$task->id}", $task->status, $task->name);
    }
    $taskId = intVal(GitPlanbox_Util::readline("Which task would you like to work on? "));
    if (!isset($tasksByTaskId[$taskId]))
    {
      throw new Exception("Invalid task id {$taskId} for story {$storyId}, expected one of: " . implode(', ', array_keys($tasksByTaskId)) . '.');
    }
    $this->_startTimerForTask($session, $storyId, $taskId);
  }

  private function _startTimerForTask($session, $storyId, $taskId)
  {
    if (!$storyId) throw new Exception("Expected storyId, got " . var_export($storyId, true));
    if (!$taskId) throw new Exception("Expected taskId, got " . var_export($taskId, true));

    $postData = array(
                  'story_id' => $storyId,
                  'task_id'  => $taskId,
                  'status'  => 'inprogress',
                );
    $session->post('update_task', $postData);
    print("Started timer for story #{$storyId}, task #{$taskId}.\n");
  }

}
