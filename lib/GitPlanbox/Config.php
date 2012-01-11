<?php

class GitPlanbox_Config
{

  private $_email          = NULL;
  private $_password       = NULL;
  private $_productid      = NULL;
  private $_author         = NULL;
  private $_branchtemplate = '#parent#-#name#-#storyid#-#author#';
  private $_branchregex    = '/^[a-zA-Z0-9\_]+$/';

  const PLANBOX_API_BASE_URI = 'http://www.planbox.com/api/';

  private function __construct()
  {
    // Use GitPlanbox_Config::get() instead.
    return $this;
  }

  public static function get()
  {
    $config = new self();

    // Load all the planbox config settings from git
    $vars = array();
    exec("git config --get-regexp \"planbox\..*\"", $resultArray);
    foreach ($resultArray as $resultRow)
    {
      $splitPosition = strpos($resultRow, " ");
      $key           = substr($resultRow, 0, $splitPosition);
      $value         = substr($resultRow, $splitPosition + 1);
      $vars[$key]    = $value;
    }

    // Check our prerequisites
    if (!isset($vars['planbox.email']))
    {
      print("Error: we couldn't find your planbox email. Add it to your ~/.gitconfig under planbox.email\n");
      exit(1);
    }
    $config->_email = $vars['planbox.email'];

    if (!isset($vars['planbox.password']))
    {
      print("Error: we couldn't find your password. Add it to your ~/.gitconfig under planbox.password\n");
      exit(1);
    }
    $config->_password = $vars['planbox.password'];

    if (!isset($vars['planbox.productid']))
    {
      print("Error: we couldn't find your product id. Add it to your .git/config under planbox.productid\n");
      exit(1);
    }
    $config->_productid = $vars['planbox.productid'];

    if (isset($vars['planbox.author']))
    {
      $config->_author = $vars['planbox.author'];
    } else {
      // Use the current system user, like: `whoami`
      $posixUser = posix_getpwuid(posix_geteuid());
      $config->_author = $posixUser['name'];
    }

    if (isset($vars['planbox.branchtemplate']))
    {
      $config->_branchtemplate = $vars['planbox.branchtemplate'];
    }

    if (isset($vars['planbox.branchregex']))
    {
      $config->_branchregex = $vars['planbox.branchregex'];
    }

    return $config;
  }

  public static function generateUrlForPath($path)
  {
    return self::PLANBOX_API_BASE_URI . $path;
  }

  public function email()
  {
    return $this->_email;
  }

  public function password()
  {
    return $this->_password;
  }

  public function productid()
  {
    return $this->_productid;
  }

  public function author()
  {
    return $this->_author;
  }

  public function branchtemplate()
  {
    return $this->_branchtemplate;
  }

  public function branchregex()
  {
    return $this->_branchregex;
  }

}
