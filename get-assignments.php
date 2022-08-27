<?php
  require_once __DIR__ . '/includes.php';

  header('Content-Type: text/plain');
  header('Content-Disposition: inline');

  $assignment_url = $_GET["assignment_url"] ?? "%";

  // Connect to the database
  $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE, $DB_PORT);
  if (!$conn) {
    http_response_code(500);
    die('Erro de banco de dados: ' . mysqli_connect_error());
  }
  mysqli_query($conn, "SET NAMES 'utf8'");

  $sql = $conn->prepare("
  SELECT DISTINCT `assignment_url` AS url
  FROM `sub_submissions`
  WHERE `assignment_url` LIKE ?
  ORDER BY 1
  ");
  $sql->bind_param("s", $assignment_url);
  $sql->execute();
  $result = $sql->get_result();

  while($row = $result->fetch_assoc()) {
    echo $row["url"] . "\n";
  }

?>