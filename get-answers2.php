<?php
  require_once __DIR__ . '/includes.php';

  header('Content-Type: application/json; charset=utf-8');
  
  $requesting_username = getUsernameFromToken();

  $data = json_decode(file_get_contents('php://input'), true);
  $assignment_url = $data["assignment_url"];
  $username = $data["username"] ?? $requesting_username;
  $submission_type = $data["submission_type"] ?? "batch";

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
  SELECT id, assignment_url, username, submission_type, question_index, score, answer
  FROM `sub_submissions`
  WHERE id IN (
    SELECT max(`id`) AS id
    FROM `sub_submissions`
    WHERE `submission_type` LIKE ?
    AND `assignment_url` LIKE ?
    AND `username` LIKE ?
    GROUP BY assignment_url, username, question_index
    ORDER BY assignment_url, username, question_index
  )
  ORDER BY assignment_url, username, question_index;
  ");
  $sql->bind_param("sss", $submission_type, $assignment_url, $username);
  $sql->execute();
  $result = $sql->get_result();

  $rows = $result->fetch_all(MYSQLI_ASSOC);

  echo json_encode($rows);
?>