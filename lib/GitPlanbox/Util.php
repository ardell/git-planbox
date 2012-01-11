<?php

class GitPlanbox_Util
{

  /**
   * Figure out how long ago a given DateTime was,
   * for instance '3 days ago', '1 year ago', etc.
   *
   * @param DateTime $dateTime The date in question.
   * @return string How long ago the date was (as a string).
   */
  public static function timeAgo($dts)
  {
    $dtsAsUnixTime = strtotime($dts);
    $now           = strtotime('now');
    $diff          = $now - $dtsAsUnixTime;
    $periods       = array(
      'decade' => 315705600,
      'year'   => 31570560,
      'month'  => 2630880,
      'week'   => 604800,
      'day'    => 86400,
      'hour'   => 3600,
      'minute' => 60,
      'second' => 1,
    );

    if ($diff < 1) return 'just now';

    foreach ($periods as $label => $seconds)
    {
      if ($seconds < $diff)
      {
        $quantity = round($diff / $seconds);     // Something like '3 weeks ago'
        if ($quantity > 1) $label = "{$label}s"; // Pluralize
        return "{$quantity} {$label} ago";
      }
    }

    return 'just now';
  }

  public static function currentGitBranchName()
  {
    // Get branch name from git
    exec("git symbolic-ref -q HEAD", $resultArray, $returnCode);
    if ($returnCode !== 0) throw new Exception("Could not get current branch name.");

    // Return the first line
    $branchName = array_pop($resultArray);
    return trim(str_replace('refs/heads/', '', $branchName));
  }

  public static function currentStoryId()
  {
    $currentGitBranch = self::currentGitBranchName();
    $matches = array();
    preg_match_all("/[0-9]+/", $currentGitBranch, $matches);
    if (count($matches) != 1) return NULL;
    $match = array_shift($matches);
    return intVal($match[0]);
  }

  public static function readline($prompt)
  {
    if (PHP_OS == 'WINNT') {
      print($prompt);
      $line = stream_get_line(STDIN, 1024, PHP_EOL);
    } else {
      $line = readline($prompt);
    }

    return $line;
  }

}
