<?php

class GitPlanbox_Start extends CLIMax_BaseCommand
{

  public function run($arguments, CLImaxController $cliController)
  {
    // First try the storyId specified on the command line
    $storyId = NULL;
    if (isset($arguments[0]))
    {
      $storyId = ltrim($arguments[0], "# ");
    }

    // If no storyId was specified on the command line, try to
    // parse it out of the current branch name
    if (!$storyId)
    {
      $storyId = GitPlanbox_Util::currentStoryId();
    }

    // If we still don't have a story id, tell the user to please
    // specify one
    if (!$storyId)
    {
      throw new Exception("Couldn't auto-detect a story_id, please specify one like so: git planbox start 12345");
    }

    // Get the story from Planbox
    $session = GitPlanbox_Session::create();
    try {
      $story = $session->post('get_story', array('story_id' => $storyId));
    } catch (GitPlanbox_ApplicationErrorException $e) {
      throw new Exception("Unable to fetch story {$storyId} from Planbox.");
    }

    // Switch branches
    $this->_switchBranches($story);

    // Update timers
    $this->_stopRunningTimers($session);
    $this->_startTimer($session, $story);

    // Success!
    return 0;
  }

  private function _switchBranches($targetStory)
  {
    // Don't switch branches if we're not switching stories
    $currentStoryId = GitPlanbox_Util::currentStoryId();
    if ($targetStory->id == $currentStoryId) return;

    // Confirm that they want to branch off the current branch
    $currentBranch = GitPlanbox_Util::currentGitBranchName();
    print("Starting story: {$targetStory->name}\n");
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
      $branchName = $this->_buildBranchName($branch, $targetStory, $currentBranch);

      // Create the branch
      $command = "git checkout -b {$branchName} {$currentBranch}";
      exec($command, $output, $returnCode);
      if ($returnCode !== 0) throw new Exception("Error creating new branch.");
    }
  }

  private function _buildBranchName($branchName, $story, $parent)
  {
    $config         = GitPlanbox_Config::get();
    $branchTemplate = $config->branchtemplate();

    // Substitute variables
    $substitutions = array(
      'author'  => $config->author(),
      'name'    => $branchName,
      'parent'  => $parent,
      'storyid' => $story->id,
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
      $response = GitPlanbox_Util::readline("Pause (p), finish (f), or ignore (i) these tasks? [P/f/i] ");
      if ($response == '' || strtolower($response) == 'p' || strtolower($response) == 'pause')
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
      } else if (strtolower($response) == 'f' || strtolower($response) == 'finish') {
        foreach ($inProgress as $story)
        {
          foreach ($story->tasks as $task)
          {
            if ($task->status != 'inprogress') continue;

            // Stop the timer
            $postData = array(
              'story_id' => $story->id,
              'task_id'  => $task->id,
              'status'   => 'completed',
            );
            $session->post('update_task', $postData);
          }
        }
        print("Finished " . count($inProgress) . " stories.\n");
      }
    }
  }

  private function _startTimer($session, $story)
  {
    // Don't do anything if there are no tasks
    if (count($story->tasks) < 1)
    {
      print("Not starting timer because story {$story->id} has no tasks.");
      return;
    }

    // If we have only one task, start the timer for that task
    if (count($story->tasks) == 1)
    {
      $task = $story->tasks[0];
      $this->_startTimerForTask($session, $story, $task->id);
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
      throw new Exception("Invalid task id {$taskId} for story {$story->id}, expected one of: " . implode(', ', array_keys($tasksByTaskId)) . '.');
    }
    $this->_startTimerForTask($session, $story, $taskId);
  }

  private function _startTimerForTask($session, $story, $taskId)
  {
    if (!$story) throw new Exception("Expected story, got " . var_export($story, true));
    if (!$taskId) throw new Exception("Expected taskId, got " . var_export($taskId, true));

    // Find the task
    $task = NULL;
    foreach ($story->tasks as $t)
    {
        print("Found task id {$t->id}\n");
        if ($t->id == $taskId)
        {
            $task = $t;
            break;
        }
    }
    if (!$task) throw new Exception("Couldn't find that task with id: " . var_export($taskId, true));

    $postData = array(
                  'story_id' => $story->id,
                  'task_id'  => $taskId,
                  'status'   => 'inprogress',
                );

    // If the task is unassigned, assign it to the current user
    if ($task->resource_id === NULL)
    {
        $postData['resource_id'] = GitPlanbox_Config::resourceid();
    }

    $session->post('update_task', $postData);
    print("Started timer for story #{$story->id}, task #{$taskId}.\n");
  }

}
