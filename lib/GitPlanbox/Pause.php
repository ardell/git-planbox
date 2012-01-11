<?php

class GitPlanbox_Pause extends CLIMax_BaseCommand
{

  public function run($arguments, CLImaxController $cliController)
  {
    // First look for the story id in the current git branch name
    $storyId = GitPlanbox_Util::currentStoryId();

    // If not found, ask the user for the story id they're working on
    if (!$storyId)
    {
      $storyId = intVal(GitPlanbox_Util::readline("I couldn't auto-detect which story you're working on, what is the story id?"));
      if (!$storyId) throw new Exception("I didn't understand that story id.");
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

    // If there are no inprogress tasks, tell the user
    if (!isset($tasksByStatus['inprogress']) || count($tasksByStatus['inprogress']) < 1)
    {
      print("There are no tasks currently in-progress for story {$storyId}.\n");
      return 0;
    }

    // If there's only one status:inprogress task, set it to status:pending
    if (count($tasksByStatus['inprogress']) == 1)
    {
      $task = array_shift($tasksByStatus['inprogress']);
      $this->_pauseTimerForTask($session, $storyId, $task->id);
      return 0;
    }

    // Otherwise ask the user which task they'd like to pause (allow pausing all too!)
    foreach ($story->tasks as $task)
    {
      $tasksByTaskId[$task->id] = $task;
      printf("%8s %10s - %-50s\n", "#{$task->id}", $task->status, $task->name);
    }
    $taskId = GitPlanbox_Util::readline("Which task id would you like to pause? You can also say all... ");
    if (strtolower($taskId) === 'all')
    {
      foreach ($tasksByStatus['inprogress'] as $task)
      {
        $this->_pauseTimerForTask($session, $storyId, $task->id);
      }
    } elseif (isset($tasksByTaskId[intVal($taskId)])) {
      $task = $tasksByTaskId[intVal($taskId)];
      $this->_pauseTimerForTask($session, $storyId, $task->id);
    } else {
      if (!$storyId) throw new Exception("I didn't understand that story id.");
    }

    return 0;
  }

  private function _pauseTimerForTask($session, $storyId, $taskId)
  {
    if (!$storyId) throw new Exception("Expected storyId, got " . var_export($storyId, true));
    if (!$taskId) throw new Exception("Expected taskId, got " . var_export($taskId, true));

    $postData = array(
                  'story_id' => $storyId,
                  'task_id'  => $taskId,
                  'status'  => 'pending',
                );
    $session->post('update_task', $postData);
    print("Paused timer for story #{$storyId}, task #{$taskId}.\n");
  }

}
