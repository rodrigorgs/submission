<?php
  require_once __DIR__ . '/includes.php';

  $requesting_username = getUsernameFromToken();

  if ($requesting_username != $ADMIN_USERNAME) {
    http_response_code(403);
    echo "Forbidden.";
    exit;
  }

  $data = json_decode(file_get_contents('php://input'), true);
  $assignment_urls = $data["assignment_urls"];
  $submission_type = $data["submission_type"] ?? "batch";

  // Connect to the database
  $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE, $DB_PORT);
  if (!$conn) {
    http_response_code(500);
    die('Erro de banco de dados: ' . mysqli_connect_error());
  }
  mysqli_query($conn, "SET NAMES 'utf8'");

  $in = join(',', array_fill(0, count($assignment_urls), '?'));
  $sql = $conn->prepare("
  SELECT username, CONCAT(assignment_url, ' ', question_index) AS question,
    assignment_url, question_index, MAX(score) AS score, COUNT(*) AS count
  FROM `sub_submissions`
  WHERE `assignment_url` IN ($in)
  AND `submission_type` = ?
  GROUP BY username, assignment_url, question_index
  ORDER BY username, assignment_url, question_index
  ");
  
  $sql->bind_param(str_repeat('s', count($assignment_urls)) . 's', ...array_merge($assignment_urls, [$submission_type]));
  $sql->execute();
  $result = $sql->get_result();
  $rows = $result->fetch_all(MYSQLI_ASSOC);

  $by_question = array_group_by($rows, 'question');
  $num_questions = count(array_keys($by_question));

  $by_username = array_group_by($rows, 'username');
  $usernames = array_keys($by_username);

  $assoc = array_group_by($rows, 'username', 'question');

  $mime = getBestSupportedMimeType(array('text/csv', 'application/json', 'text/html'));
  error_log("mime: $mime");
  if ($mime == 'text/html') {
    header('Content-Type: text/html');
    echo "<table border=\"1\">";
    
    foreach ($usernames as $username) {
      echo "<tr>";
      echo "<td>$username</td>";
      for ($i = 0; $i < $num_questions; $i++) {
        $question = array_keys($by_question)[$i];
        $parts = explode(' ', $question);
        $assignment_url = $parts[0];
        $question_index = intval($parts[1]);
        $query = http_build_query(array(
          "url" => $assignment_url,
          "username" => $username,
          "question_index" => $question_index,
          "submission_type" => $submission_type,
          "token" => getBearerToken()
        ));
        
        if (array_key_exists($question, $assoc[$username])) {
          $metadata = $assoc[$username][$question][0];
        } else {
          $metadata = ["score" => NULL];
        }
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
  } else if ($mime == 'application/json') {
    print_r($assoc);
  } else {
    // // TODO:
    // header('Content-Type: text/csv');
    // header('Content-Disposition: inline');

    // foreach ($table as $row) {
    //   echo implode("\t", $row) . "\n";
    // }
  }

?>