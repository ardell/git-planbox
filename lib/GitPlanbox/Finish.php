<?php
# vim: set ts=2 sw=2:

class GitPlanbox_Finish extends CLIMax_BaseCommand
{

  public function run($arguments, CLImaxController $cliController)
  {
    // Look for a story id on the command line
    $storyId = NULL;
    if (isset($arguments[0]))
    {
      $storyId = ltrim($arguments[0], "# ");
    }

    // Look for the story id in the current git branch name
    if (!$storyId)
    {
      $storyId = GitPlanbox_Util::currentStoryId();
    }

    // Require a storyId to move forward
    if (!$storyId)
    {
      throw new Exception("I was unable to auto-detect which story id you would like to pause, please specify it like this: 'git planbox pause 12345'");
    }

    // Fetch the story from Planbox
    $session = GitPlanbox_Session::create();
    try {
      $story = $session->post('get_story', array('story_id' => $storyId));
    } catch (GitPlanbox_ApplicationErrorException $e) {
      throw new Exception("Unable to fetch story {$storyId} from Planbox.");
    }

    // Arrange tasks in useful ways
    $tasksByStatus = array();
    $tasksByTaskId = array();
    foreach ($story->tasks as $task)
    {
      $tasksByStatus[$task->status][] = $task;
      $tasksByTaskId[$task->id] = $task;
    }

    // If there are no status:inprogress tasks, tell the user and return
    if (count($tasksByStatus['inprogress']) < 1)
    {
      print("There are no in-progress tasks for story #{$storyId}.\n");
      return 0;
    }

    // If there's only one status:inprogress task, set it to status:completed
    if (count($tasksByStatus['inprogress']) == 1)
    {
      $task = array_shift($tasksByStatus['inprogress']);
      $this->_stopTimerForTask($session, $storyId, $task->id);
      return 0;
    }

    // Otherwise ask the user which task they'd like to stop (allow pausing all too!)
    foreach ($tasksByStatus['inprogress'] as $task)
    {
      printf("%8s %10s - %-50s\n", "#{$task->id}", $task->status, $task->name);
    }
    $taskId = GitPlanbox_Util::readline("Which task id would you like to finish? You can also say all... ");
    if (strtolower($taskId) === 'all')
    {
      foreach ($tasksByStatus['inprogress'] as $task)
      {
        $this->_stopTimerForTask($session, $storyId, $task->id);
      }
    } elseif (isset($tasksByTaskId[intVal($taskId)])) {
      $task = $tasksByTaskId[intVal($taskId)];
      $this->_stopTimerForTask($session, $storyId, $task->id);
    } else {
      if (!$storyId) throw new Exception("I didn't understand that task id.");
    }

    return 0;
  }

  private function _stopTimerForTask($session, $storyId, $taskId)
  {
    if (!$storyId) throw new Exception("Expected storyId, got " . var_export($storyId, true));
    if (!$taskId) throw new Exception("Expected taskId, got " . var_export($taskId, true));

    $postData = array(
                  'story_id' => $storyId,
                  'task_id'  => $taskId,
                  'status'   => 'completed',
                );
    $session->post('update_task', $postData);
    print("Stopped timer for story #{$storyId}, task #{$taskId}.\n");
  }

}
