<?php
  use PHPMailer\PHPMailer\PHPMailer;
  use PHPMailer\PHPMailer\SMTP;
  use PHPMailer\PHPMailer\Exception;
  
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
  $replyTo = $data["replyTo"] ?? "admin@example.com";

  header('Content-Type: text/csv');

  $pairs = array_combine($usernames, $emails);
  foreach ($pairs as $username => $email) {
    $password = getPasswordForUser($username);
    echo "$password\r\n";

    if (!$dryRun) {
      $to      = $email;
      $body = str_replace("%password%", $password, $message);

      $mail = new PHPMailer(true);
      try {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->isSMTP();
        $mail->Host       = $MAIL_HOST;
        $mail->Port       = $MAIL_PORT;
        $mail->SMTPAuth   = $MAIL_SMTP_AUTH;
        $mail->Username   = $MAIL_USERNAME;
        $mail->Password   = $MAIL_PASSWORD;
        if ($MAIL_SMTP_SECURE) {
          $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->setFrom($MAIL_USERNAME);
        $mail->addAddress($to);
        $mail->addReplyTo($replyTo);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
      } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
      }
    }
  }
?>