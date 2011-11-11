<?php

class GitPlanbox_Show extends CLIMax_BaseCommand
{

  public function run($arguments, CLImaxController $cliController)
  {
    // If we have an argument, show the story with _that_ id
    $storyId = isset($arguments[0]) ? $arguments[0] : NULL;

    // Otherwise try to figure out what story we're working on
    // based on the current branch name
    if (!$storyId)
    {
      $currentBranchName = GitPlanbox_Util::currentGitBranchName();

      // Try to parse out a story id
      preg_match("/[0-9]{5,8}/", $resultAsString, $matches);
      if ($matches) $storyId = array_pop($matches);
    }

    // If we STILL don't know what story to show, throw a usage exception
    if (!$storyId)
    {
      throw new Exception("Please specify a story_id to show.");
    }

    // Fetch the story
    $session = GitPlanbox_Session::create();
    try {
      $story   = $session->post('get_story', array('story_id' => $storyId));
    } catch (GitPlanbox_ApplicationErrorException $e) {
      throw new Exception("Unable to fetch story {$storyId} from Planbox.");
    }

    // Lines for all stories
    $storyText  = '';
    $lineFormat = "%-10s %s\n";
    $storyLines = array(
      'Story'   => "#{$story->id}",
      'Name'    => $story->name,
      'Type'    => $story->type,
      'Status'  => $story->status,
      'Created' => GitPlanbox_Util::timeAgo($story->created_on),
    );
    foreach ($storyLines as $key => $value)
    {
      $storyText .= sprintf($lineFormat, "{$key}:", $value);
    }

    // Optional fields
    $optionalLines = array();
    if ($story->completed_on)
    {
      $optionalLines['Completed'] = GitPlanbox_Util::timeAgo($story->completed_on);
    }
    foreach ($optionalLines as $key => $value)
    {
      $storyText .= sprintf($lineFormat, "{$key}:", $value);
    }

    if ($story->description)
    {
      $storyText .= "\nDescription:\n  {$story->description}\n";
    }

    if ($story->tasks)
    {
      $storyText .= "\nTasks:\n";
      foreach ($story->tasks as $task)
      {
        $storyText .= sprintf(
                        // id     statu durat estimate      name
                        "  #%-10s %-10s %1.1f/%1.1f hours   %-30s\n",
                        $task->id,
                        $task->status,
                        $task->duration,
                        $task->estimate,
                        $task->name
                      );
      }
    }

    if ($story->comments)
    {
      $storyText .= "\nComments:\n";
      foreach ($story->comments as $comment)
      {
        $storyText .= "  " . GitPlanbox_Util::timeAgo($comment->date) . "\n  {$comment->text}\n\n";
      }
    }

    print($storyText);

    return 0;
  }

}
