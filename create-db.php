<?php
  /*
   * Can be run from the command line or from a web browser.
   * If run from a web browser, needs to be run with a valid JWT token
   * for the admin user.
   */
  require_once __DIR__ . '/includes.php';
  $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE);
  if (!$conn) {
    die('Erro de banco de dados: ' . mysqli_connect_error());
  }
  mysqli_query($conn, "SET NAMES 'utf8'");

  $username = getUsernameFromToken();
  if ($username != $ADMIN_USERNAME && php_sapi_name() != "cli") {
    http_response_code(403);
    echo "Forbidden.";
    exit;
  }

  /*
   * If question is NULL, answer is the answer as string.
   * If question is not NULL, answer is the answers as a JSON-encoded array.
   */
  mysqli_query($conn, "CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_url VARCHAR(1024) NOT NULL,
    username VARCHAR(64) NOT NULL,
    answer TEXT,
    question_index INTEGER,
    question_type VARCHAR(16),
    timestamp DATETIME
  );");

  try {
    mysqli_query($conn, 
      "CREATE INDEX idx_assignment_url ON submissions(assignment_url);");
  } catch (mysqli_sql_exception $e) {
    // Ignore if index already exists.
  }

  try {
    mysqli_query($conn, 
      "CREATE INDEX idx_username_assignment_url ON submissions(assignment_url, username);");
  } catch (mysqli_sql_exception $e) {
    // Ignore if index already exists.
  }

?>