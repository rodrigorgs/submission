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
  SELECT username, question_index, MAX(score) AS score, COUNT(*) AS count
  FROM `sub_submissions`
  WHERE `assignment_url` = ?
  AND `submission_type` = ?
  GROUP BY username, question_index
  ORDER BY username, question_index
  ");
  $sql->bind_param("ss", $assignment_url, $submission_type);
  $sql->execute();
  $result = $sql->get_result();
  $rows = $result->fetch_all(MYSQLI_ASSOC);

  $by_question = array_group_by($rows, 'question_index');
  $max_question_index = count(array_keys($by_question)) - 1;

  $by_username = array_group_by($rows, 'username');
  $usernames = array_keys($by_username);

  $assoc = array_group_by($rows, 'username', 'question_index');

  $mime = getBestSupportedMimeType(array('text/csv', 'text/html'));
  error_log("mime: $mime");
  if ($mime == 'text/html') {
    header('Content-Type: text/html');
    echo "$assignment_url<br>";
    echo "submission_type: $submission_type<br>";
    echo "token: " . getBearerToken() . "<br><br>";
    echo "<table border=\"1\">";
    
    foreach ($usernames as $username) {
      echo "<tr>";
      echo "<td>$username</td>";
      for ($question_index = 0; $question_index <= $max_question_index; $question_index++) {
        $query = http_build_query(array(
          "url" => $assignment_url,
          "username" => $username,
          "question_index" => $question_index,
          "submission_type" => $submission_type,
          "token" => getBearerToken()
        ));
          
        $metadata = $assoc[$username][$question_index][0];
        if ($metadata["score"]) {
          $color = 'palevioletred';
          if ($metadata["score"] >= 1.0) {
            $color = 'lightgreen';
          }
          echo "<td style=\"background: $color;\"><a href=\"get-answer.php?$query\">" . $metadata["score"] . "</a></td>";
        } else {
          echo "<td>&nbsp;</td>";
        }
      }
    
      echo "</tr>";
    }
    echo "</table>";
  } 
  // else {
  //   header('Content-Type: text/csv');
  //   header('Content-Disposition: inline');

  //   foreach ($table as $row) {
  //     echo implode("\t", $row) . "\n";
  //   }
  // }

?>