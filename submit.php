<?php
  require_once __DIR__ . '/includes.php';

  $data = json_decode(file_get_contents('php://input'), true);
  $question_index = $data["question_index"] ?? 0;
  $question_type = $data["question_type"];
  $answer = $data["answer"];
  $submission_type = $data["submission_type"];
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

  /////////////

  $all_answers = $answer;
  if (!is_array($all_answers)) {
    $all_answers = array($all_answers);
  }

  $timestamp = (new DateTime())->format('Y-m-d H:i:s.u');

  $conn->begin_transaction();
  try {
    
    foreach ($all_answers as $single_answer) {
      error_log("question_index " . $question_index);
      $sql = $conn->prepare("INSERT INTO submissions (
        timestamp,
        assignment_url,
        username,
        answer,
        question_index,
        question_type,
        submission_type)
        VALUES (?, ?, ?, ?, ?, ?, ?);");
      $sql->bind_param("ssssiss", 
        $timestamp,
        $assignment_url,
        $username,
        $single_answer,
        $question_index,
        $question_type,
        $submission_type);
      $result = $sql->execute();

      $question_index++;
      file_put_contents('php://stderr', print_r("QWEQWEQWE " . $question_index));
    }

    $conn->commit();
  } catch (mysqli_sql_exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo "Error: " . $e->getMessage();
    exit;
  }
  $conn->close();
?>