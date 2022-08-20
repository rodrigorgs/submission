<?php
  require_once __DIR__ . '/includes.php';

  header('Content-Type: text/plain');
  header('Content-Disposition: inline');

  $assignment_url = $_GET["url"];
  $username = $_GET["username"];
  $question_index = intval($_GET["question_index"]);
  $submission_type = $_GET["submission_type"] ?? "batch";

  $requesting_username = getUsernameFromToken();
  if ($requesting_username != $ADMIN_USERNAME) {
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
  SELECT answer
  FROM `sub_submissions`
  WHERE `submission_type` = 'batch'
  AND `assignment_url` = ?
  AND `username` = ?
  AND `question_index` = ?
  AND `submission_type` = ?
  ORDER BY timestamp DESC
  LIMIT 1;
  ");
  $sql->bind_param("ssis", $assignment_url, $username, $question_index, $submission_type);
  $sql->execute();
  $result = $sql->get_result();

  while($row = $result->fetch_assoc()) {
    echo $row["answer"];
  }

?>