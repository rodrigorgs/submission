<?php
  /*
   * Can be run from the command line or from a web browser.
   * If run from a web browser, needs to be run with a valid JWT token
   * for the admin user.
   */
  require_once __DIR__ . '/includes.php';

  $username_to_query = $_GET["username"];
  
  $username = getUsernameFromToken();
  if ($username != $ADMIN_USERNAME) {
    http_response_code(403);
    echo "Forbidden.";
    exit;
  }

  echo(getPasswordForUser($username_to_query));
  
?>