<?php
  require_once __DIR__ . '/includes.php';

  header('Content-Type: application/json; charset=utf-8');
  
  $requesting_username = getUsernameFromToken();
  $assignment_url = $_GET["url"];
  $username = $_GET["username"] ?? $requesting_username;
  $submission_type = $_GET["submission_type"] ?? "batch";

  if ($requesting_username != $ADMIN_USERNAME && $username != $requesting_username) {
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
  WHERE id IN (
    SELECT max(`id`) AS id
    FROM `sub_submissions`
    WHERE `submission_type` LIKE ?
    AND `assignment_url` = ?
    AND `username` = ?
    GROUP BY assignment_url, username, question_index
    ORDER BY assignment_url, username, question_index
  )
  ORDER BY assignment_url, username, question_index;
  ");
  $sql->bind_param("sss", $submission_type, $assignment_url, $username);
  $sql->execute();
  $result = $sql->get_result();

  $answers = [];
  while ($row = $result->fetch_assoc()) {
    $answers[] = $row["answer"];
  }

  echo json_encode($answers);
?>