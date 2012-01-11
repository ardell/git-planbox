<?php

class GitPlanbox_Finish extends CLIMax_BaseCommand
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

    // If there's only one status:inprogress task, set it to status:completed
    if (count($tasksByStatus['inprogress']) == 1)
    {
      $task = array_shift($tasksByStatus['inprogress']);
      $this->_stopTimerForTask($session, $storyId, $task->id);
      return 0;
    }

    // Otherwise ask the user which task they'd like to stop (allow pausing all too!)
    foreach ($story->tasks as $task)
    {
      $tasksByTaskId[$task->id] = $task;
      printf("%8s %10s - %-50s\n", "#{$task->id}", $task->status, $task->name);
    }
    $taskId = GitPlanbox_Util::readline("Which task id would you like to finish? You can also say all... ");
    if (strtolower($taskId) === 'all')
    {
      foreach ($tasksByTaskId as $task)
      {
        $this->_stopTimerForTask($session, $storyId, $task->id);
      }
    } elseif (isset($tasksByTaskId[intVal($taskId)])) {
      $task = $tasksByTaskId[intVal($taskId)];
      $this->_stopTimerForTask($session, $storyId, $task->id);
    } else {
      if (!$storyId) throw new Exception("I didn't understand that task id.");
    }

    // Mark the STORY as completed now
    $postData = array(
                  'story_id' => $storyId,
                  'status'   => 'completed',
                );
    $session->post('update_story', $postData);
    print("Marked story {$storyId} as completed.\n");

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
