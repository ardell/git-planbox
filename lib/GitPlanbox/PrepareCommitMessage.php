<?php

class GitPlanbox_PrepareCommitMessage extends CLIMax_BaseCommand
{

  public function run($arguments, CLImaxController $cliController)
  {
    // Check requirements
    if (!isset($arguments[0])) throw new Exception("Please specify a commit file.");
    if (!file_exists($arguments[0])) throw new Exception("Commit file does not exist.");

    // Try to parse the story id out of the current branch name.
    // If we're unable, don't bother amending the commit message.
    $storyId = GitPlanbox_Util::currentStoryId();
    if (!$storyId) return 0;

    // Fetch the story from Planbox
    $session = GitPlanbox_Session::create();
    try {
      $story = $session->post('get_story', array('story_id' => $storyId));
    } catch (GitPlanbox_ApplicationErrorException $e) {
      // Couldn't find the story, don't amend the commit message
      return 0;
    }

    // Get a list of tasks that are in progress on this story
    $inProgressTasks = array();
    foreach ($story->tasks as $task)
    {
      if ($task->status == 'inprogress') array_push($inProgressTasks, $task->name);
    }
    $allInProgressTasks = implode(', ', $inProgressTasks);

    // Prepare the _message_ part of the commit message
    $parts = array();
    if ($story->name) array_push($parts, $story->name);
    if ($allInProgressTasks) array_push($parts, $allInProgressTasks);
    $combinedName = implode(' - ', $parts);

    // Update the commit message
    $commitMessageFile = $arguments[0];
    $commitMessage     = file_get_contents($commitMessageFile);
    $newContents       = <<<COMMIT_MESSAGE
[#{$storyId}] {$combinedName}.
{$commitMessage}
COMMIT_MESSAGE;
    file_put_contents($commitMessageFile, $newContents);

    // Success!
    return 0;
  }

}
