<?php
  require_once __DIR__ . '/includes.php';

  $data = json_decode(file_get_contents('php://input'), true);
  $question_index = $data["question_index"] ?? 0;
  $question_type = $data["question_type"];
  $answer = $data["answer"];
  $submission_type = $data["submission_type"] ?? NULL;
  $assignment_url = $data["assignment_url"] ?? $_SERVER['HTTP_REFERER'];
  $timestamp = (new DateTime())->format('Y-m-d H:i:s.u');

  // Connect to the database
  $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE, $DB_PORT);
  if (!$conn) {
    http_response_code(500);
    die('Erro de banco de dados: ' . mysqli_connect_error());
  }
  mysqli_query($conn, "SET NAMES 'utf8'");

  $username = getUsernameFromToken();
  if ($username == $ADMIN_USERNAME) {
    if ($question_type != "control") {
      http_response_code(422);
      die("Only control submissions are allowed");
    }

    error_log("Admin control");
    if ($answer == "open") {
      openNextQuestion($conn, $assignment_url);
    } else if ($answer == "close") {
      closeCurrentQuestion($conn, $assignment_url);
    } else {
      http_response_code(422);
      die("Invalid answer. Must be open or close.");
    }
  } else if ($question_type == "clicker") {
    $question_index = getCurrentQuestionIndex($conn, $assignment_url);
    error_log("Anonymous or no index; current q = $question_index");
    if (is_null($question_index)) {
      error_log("No open questions");
      http_response_code(403);
      die("No open questions.");
    } else {
      error_log("Will add submission");
      addSubmission($conn, $timestamp, $assignment_url, $username, $answer, $question_index, $question_type, $submission_type);
      error_log("Did add submission");
    }
  } else {
    error_log("Regular submission");
    $all_answers = $answer;
    if (!is_array($all_answers)) {
      $all_answers = array($all_answers);
    }
  
    $conn->begin_transaction();
    try {
      
      foreach ($all_answers as $single_answer) {
        error_log("question_index " . $question_index);
        addSubmission($conn, $timestamp, $assignment_url, $username, $single_answer, $question_index, $question_type, $submission_type);
  
        $question_index++;
      }
  
      $conn->commit();
    } catch (mysqli_sql_exception $e) {
      $conn->rollback();
      http_response_code(500);
      echo "Error: " . $e->getMessage();
      exit;
    }
  }

  $conn->close();
?>