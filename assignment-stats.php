<?php
  require_once __DIR__ . '/includes.php';

  header('Content-Type: text/csv');
  header('Content-Disposition: inline');

  $assignment_url = $_GET["url"];
  $submission_type = $_GET["submission_type"] ?? "batch";

  // Connect to the database
  $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE, $DB_PORT);
  if (!$conn) {
    http_response_code(500);
    die('Erro de banco de dados: ' . mysqli_connect_error());
  }
  mysqli_query($conn, "SET NAMES 'utf8'");

  $sql = $conn->prepare("
  SELECT username, question_index
  FROM `sub_submissions`
  WHERE `assignment_url` = ?
  AND `submission_type` = ?
  GROUP BY username, question_index
  ORDER BY username, question_index
  ");
  $sql->bind_param("ss", $assignment_url, $submission_type);
  $sql->execute();
  $result = $sql->get_result();

  $users = array();
  $max_question_index = 0;
  while($row = $result->fetch_assoc()) {
    $question_index = $row["question_index"];
    $users[$row["username"]][intval($question_index)] = 1;
    if ($question_index > $max_question_index) {
      $max_question_index = $question_index;
    }
  }

  echo "username\ttotal\t" . implode("\t", range(0, $max_question_index)) . "\n";
  $question_range = range(0, $max_question_index);
  foreach ($users as $username => $questions) {
    echo $username . "\t" . count($questions) . "\t";

    $q = array_map(function($idx) use ($questions, $max_question_index) { return array_key_exists($idx, $questions) ? 1 : 0; }, $question_range);
    echo implode("\t", $q) . "\n";
  }

?>