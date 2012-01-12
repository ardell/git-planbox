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
    $storyId = ltrim($arguments[0], "# ");

    // Get the story from Planbox
    $session = GitPlanbox_Session::create();
    try {
      $story = $session->post('get_story', array('story_id' => $storyId));
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
    while($branch === NULL)
    {
      $branch = GitPlanbox_Util::readline("What should we name the branch? (leave blank to stay on {$currentBranch}) ");
      if ($branch !== '' && !preg_match($config->branchregex(), $branch))
      {
        print("Error: Branch names must match regex: '{$config->branchregex()}'.\n");
        $branch = NULL;
      }
    }

    if ($branch != '')
    {
      // Build the branch name based on the branch-name-template in config
      $branchName = $this->_buildBranchName($branch, $storyId, $currentBranch);

      // Create the branch
      $command = "git checkout -b {$branchName} {$currentBranch}";
      exec($command, $output, $returnCode);
      if ($returnCode !== 0) throw new Exception("Error creating new branch.");
    }

    // Update timers
    $this->_stopRunningTimers($session);
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

  private function _stopRunningTimers($session)
  {
    $config = GitPlanbox_Config::get();

    // Don't change any timers unless they're managed by this user
    if (!$config->resourceid()) return;

    // Fetch stories
    $postData = array(
      'product_id'  => $config->productid(),
      'resource_id' => $config->resourceid(),
    );
    $stories  = $session->post('get_stories', $postData);

    // Find tasks that are in progress
    $inProgress = array();
    foreach ($stories as $story)
    {
      $storyHasTasksInProgress = false;
      foreach ($story->tasks as $task)
      {
        if ($task->status == 'inprogress')
        {
          $storyHasTasksInProgress = true;
        }
      }
      if ($storyHasTasksInProgress)
      {
        array_push($inProgress, $story);
      }
    }

    // Ask the user if they'd like to pause the running timers
    if (count($inProgress) > 0)
    {
      print("The following stories have one or more tasks in progress:\n");
      foreach ($inProgress as $story)
      {
        printf("%8s %10s - %-50s\n", "#{$story->id}", $story->status, $story->name);
      }
      $response = GitPlanbox_Util::readline("Pause timers for these tasks? [Y/n] ");
      if ($response == '' || strtolower($response) == 'y' || strtolower($response) == 'yes')
      {
        foreach ($inProgress as $story)
        {
          foreach ($story->tasks as $task)
          {
            if ($task->status != 'inprogress') continue;

            // Stop the timer
            $postData = array(
              'story_id' => $story->id,
              'task_id'  => $task->id,
              'status'   => 'pending',
            );
            $session->post('update_task', $postData);
          }
        }
        print("Paused timers for " . count($inProgress) . " stories.\n");
      }
    }
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
