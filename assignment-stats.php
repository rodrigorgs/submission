<?php
  require_once __DIR__ . '/includes.php';

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

  $table = [];
  // echo "username\ttotal\t" . implode("\t", range(0, $max_question_index)) . "\n";
  $table[] = array_merge(['username', 'total'], range(0, $max_question_index));
  $question_range = range(0, $max_question_index);
  foreach ($users as $username => $questions) {
    $row = [$username, count($questions)];
    // $table[] = [$username, count($questions)];
    // echo $username . "\t" . count($questions) . "\t";

    $q = array_map(function($idx) use ($questions, $max_question_index) { return array_key_exists($idx, $questions) ? 1 : 0; }, $question_range);
    $row = array_merge($row, $q);
    // echo implode("\t", $q) . "\n";
    $table[] = $row;
  }

  $mime = getBestSupportedMimeType(array('text/csv', 'text/html'));
  error_log("mime: $mime");
  if ($mime == 'text/html') {
    header('Content-Type: text/html');
    echo "$assignment_url<br>";
    echo "submission_type: $submission_type<br>";
    echo "token: " . getBearerToken() . "<br><br>";
    echo "<table border=\"1\">";
    echo "<th>" . implode("</th><th>", array_shift($table)) . "</th>";
    foreach ($table as $row) {
      echo "<tr>";
      foreach ($row as $i => $cell) {
        $query = http_build_query(array(
          "url" => $assignment_url,
          "username" => $row[0],
          "question_index" => $i - 2,
          "submission_type" => $submission_type,
          "token" => getBearerToken()
        ));
        error_log("Bearer: " . getBearerToken());
        if ($i <= 1) {
          echo "<td>$cell</td>";
        } else {
          if ($cell == "1") {
            echo "<td><a href=\"get-answer.php?$query\">X</a></td>";
          } else {
            echo "<td>&nbsp;</td>";
          }
        }
      }
    
      echo "</tr>";
    }
    echo "</table>";
  } else {
    header('Content-Type: text/csv');
    header('Content-Disposition: inline');

    foreach ($table as $row) {
      echo implode("\t", $row) . "\n";
    }
  }

?>