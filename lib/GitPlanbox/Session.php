<?php
# vim: set ts=2 sw=2:

class GitPlanbox_Session
{

  private $_config = NULL;

  public static function create()
  {
    // Pull out config vars
    $config = GitPlanbox_Config::get();

    // Call out to planbox to start a session
    return new self($config);
  }

  private function __construct(GitPlanbox_Config $config)
  {
    $this->_config = $config;
    return $this;
  }

  public function post($path, $postData = array())
  {
    // Log in if necessary
    $this->_logIn();
    return $this->_doPost($path, $postData);
  }

  /**
   * Idempotent.
   */
  private function _logIn()
  {
    $creds = array(
      'email'    => $this->_config->email(),
      'password' => $this->_config->password(),
    );
    return $this->_doPost('login', $creds);
  }

  private function _doPost($path, $postData = array())
  {
    // Run the post
    $curl       = curl_init();
    $url        = GitPlanbox_Config::generateUrlForPath($path);
    $postString = http_build_query($postData);
    $cookieFile = $this->_cookieFile();
    curl_setopt($curl, CURLOPT_URL,             $url);
    curl_setopt($curl, CURLOPT_HEADER,          false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,  true);
    curl_setopt($curl, CURLOPT_POST,            count($postData));
    curl_setopt($curl, CURLOPT_POSTFIELDS,      $postString);
    curl_setopt($curl, CURLOPT_COOKIEFILE,      $cookieFile);
    curl_setopt($curl, CURLOPT_COOKIEJAR,       $cookieFile);
    $responseText = curl_exec($curl);

    // Get the results
    $httpCode     = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $responseData = json_decode($responseText);

    // Close cURL
    curl_close($curl);

    // Handle errors
    if ($httpCode >= 400 && $httpCode)
    {
      $e = new GitPlanbox_HttpErrorException("Error posting to {$path}");
      $e->httpCode = $httpCode;
      throw $e;
    }

    if ($responseData->code != 'ok')
    {
      throw new GitPlanbox_ApplicationErrorException("Error fetching data");
    }

    return $responseData->content;
  }

  private function _cookieFile()
  {
    return '/tmp/git-planbox-cookies';
  }

}

class GitPlanbox_HttpErrorException extends Exception {}
class GitPlanbox_ApplicationErrorException extends Exception {}
