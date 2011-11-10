<?php

class GitPlanbox_Help extends CLIMax_BaseCommand
{

  public function run($arguments, CLImaxController $cliController)
  {
    $helpMessage = <<<HELPMESSAGE

NAME
  git-planbox - A git subcommand interface for Planbox

SYNOPSIS
  git-planbox <command> [<args>]

DESCRIPTION
  git-planbox provides a simple integration between the Git version control system and the Planbox project management tool (http://www.planbox.com).

COMMANDS
  git-planbox list
    Get a list of stories to work on.

  git-planbox show
    Show tasks, details, comments, and status for a story.

  git-planbox start
    Begin working on a task. Enforces branch naming conventions and starts the timer for a task.

  git-planbox pause
    Pause the timer for a task if it is running.

  git-planbox finish
    Mark a task as finished. Stops the task's timer if it is running.

  git-planbox help
    Display this help message.

SOURCE
  http://github.com/ardell/git-planbox

SEE ALSO
  git-pivotal (http://github.com/ardell/git-pivotal)
    A git subcommand for Pivotal Tracker


HELPMESSAGE;
    print($helpMessage);
    return 0;
  }

  public function getDescription($aliases, $argLinker) {
    return 'Get help with the git-planbox command.';
  }

}
