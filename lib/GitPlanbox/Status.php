<?php

class GitPlanbox_Status extends CLIMax_BaseCommand
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
      throw new Exception("Couldn't auto-detect a story_id, please specify one like so: git planbox status 12345");
    }

    // Get the story from Planbox
    $session = GitPlanbox_Session::create();
    try {
      $story = $session->post('get_story', array('story_id' => $storyId));
    } catch (GitPlanbox_ApplicationErrorException $e) {
      throw new Exception("Unable to fetch story {$storyId} from Planbox.");
    }

    // Find all SHAs referenced in the comments
    $shas = array();
    foreach ($story->comments as $comment)
    {
      $commentShas = array();
      preg_match_all('/[a-fA-F0-9]{40}/', $comment->text, $commentShas);
      if (isset($commentShas[0])) $shas = array_merge($shas, $commentShas[0]);
    }
    $shas = array_unique($shas);

    // Run `git branch --contains {$sha}` on each sha to see where it's been merged to
    $infos = array();
    foreach ($shas as $sha)
    {
      $info = array('sha' => $sha);

      // Get the commit message for this sha
      $command = "git log -1 --pretty=oneline --abbrev-commit {$sha} 2>&1";
      $output = '';
      exec($command, $output, $returnCode);
      $commitMessage   = array_shift($output);
      $info['message'] = $commitMessage;

      // Find out which git branches contain this sha
      $command = "git branch --contains {$sha} 2>&1";
      $output = '';
      exec($command, $output, $returnCode);
      if ($returnCode === 0)
      {
        $branches = array();
        $branchesText = trim(preg_replace('/\s+/', ' ', str_replace('*', '', implode("\n", $output))));
        if ($branchesText) $branches = explode("\n", $branchesText);
        $info['branches'] = $branches;
      } else {
        $info['branches'] = array();
      }
      array_push($infos, $info);
    }

    // Pretty print the story status and the branches it's merged to
    print("Story #{$storyId} is currently: {$story->status}\n");
    if (count($infos) > 0) print("Commits:\n");
    foreach ($infos as $info)
    {
      if (isset($info['branches']) && $info['branches'])
      {
        print("  \"{$info['message']}\" is on branches: " . implode(', ', $info['branches']) . "\n");
      } else {
        print("  \"{$info['message']}\" is not on any branches.\n");
      }
    }

    // Success!
    return 0;
  }

}
