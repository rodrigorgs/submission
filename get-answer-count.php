<?php
  require_once __DIR__ . '/includes.php';

  $username = getUsernameFromToken();
  if ($username != $ADMIN_USERNAME) {
    http_response_code(403);
    echo "Forbidden.";
    exit;
  }

  $data = json_decode(file_get_contents('php://input'), true);
  $assignment_url = $data["assignment_url"] ?? $_SERVER['HTTP_REFERER'];

  // Connect to the database
  $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE, $DB_PORT);
  if (!$conn) {
    http_response_code(500);
    die('Erro de banco de dados: ' . mysqli_connect_error());
  }
  mysqli_query($conn, "SET NAMES 'utf8'");
  
  $x = getAnswerCount($conn, $assignment_url);

  echo json_encode($x);

?>