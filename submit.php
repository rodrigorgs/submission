<?php
  require_once __DIR__ . '/includes.php';

  $data = json_decode(file_get_contents('php://input'), true);
  $question_index = $data["question_index"];
  $question_type = $data["question_type"];
  $answer = $data["answer"];
  if (is_array($answer)) {
    $answer = json_encode($answer);
  }
  $assignment_url = $_SERVER['HTTP_REFERER'];

  $username = getUsernameFromToken();
  if (!$username || $username == $ADMIN_USERNAME) {
    http_response_code(401);
    echo "Invalid user.";
  }

  $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE, $DB_PORT);
  if (!$conn) {
    die('Erro de banco de dados: ' . mysqli_connect_error());
  }
  mysqli_query($conn, "SET NAMES 'utf8'");

  $sql = $conn->prepare("INSERT INTO submissions (timestamp, assignment_url, username, answer, question_index, question_type) VALUES (NOW(), ?, ?, ?, ?, ?);");
	
  $sql->bind_param("sssis", $assignment_url, $username, $answer, $question_index, $question_type);
	$result = $sql->execute();

?>