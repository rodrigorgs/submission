<?php
  /*
   * Can be run from the command line or from a web browser.
   * If run from a web browser, needs to be run with a valid JWT token
   * for the admin user.
   */
  require_once __DIR__ . '/includes.php';

  $requester = getUsernameFromToken();
  if ($requester != $ADMIN_USERNAME) {
    http_response_code(403);
    echo "Forbidden.";
    exit;
  }
  
  $data = json_decode(file_get_contents('php://input'), true);
  $usernames = $data["usernames"];
  $emails = $data["emails"];
  $dryRun = $data["dryRun"] ?? false;
  $subject = $data["subject"] ?? "Password for submissions";
  $message = $data["message"] ?? "Your password is: %password%";
  $from = $data["from"] ?? "admin@example.com";

  header('Content-Type: text/csv');

  $pairs = array_combine($usernames, $emails);
  foreach ($pairs as $username => $email) {
    $password = getPasswordForUser($username);
    echo "$password\r\n";
    if (!$dryRun) {
      $to      = $email;
      $body = str_replace("%password%", $password, $message);
      $headers = "From: $from"       . "\r\n" .
                   "Reply-To: $from" . "\r\n" .
                   "X-Mailer: PHP/" . phpversion();

      mail($to, $subject, $body, $headers);
    }
  }
?>