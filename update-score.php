<?php
  require_once __DIR__ . '/includes.php';

  header('Content-Type: text/plain');
  header('Content-Disposition: inline');

  $data = json_decode(file_get_contents('php://input'), true);
  $id = $data["id"];
  $score = $data["score"];

  $requester = getUsernameFromToken();
  if ($requester != $ADMIN_USERNAME) {
    http_response_code(403);
    echo "Forbidden.";
    exit;
  }

  // Connect to the database
  $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE, $DB_PORT);
  if (!$conn) {
    http_response_code(500);
    die('Erro de banco de dados: ' . mysqli_connect_error());
  }
  mysqli_query($conn, "SET NAMES 'utf8'");

  $sql = $conn->prepare("
  UPDATE sub_submissions
  SET score = ?
  WHERE id = ?
  ");
  $sql->bind_param("di", $score, $id);
  $sql->execute();
  
  http_response_code(200);

?>