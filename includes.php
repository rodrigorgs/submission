<?php
  if (php_sapi_name() != "cli") {
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
      http_response_code(200);
      exit(0);
    }
  }

  require_once __DIR__ . '/vendor/autoload.php';
  require_once __DIR__ . '/settings.php';
  
  if (isset($DEBUG) && $DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
  }

	use Firebase\JWT\JWT;
	use Firebase\JWT\Key;
  use Firebase\JWT\ExpiredException;

  function getPasswordForUser($username) {
    global $PASSWORD_GENERATOR_ALGORITHM,
      $PASSWORD_GENERATOR_SECRET,
      $PASSWORD_GENERATOR_LENGTH;
    $correct_password = hash_hmac($PASSWORD_GENERATOR_ALGORITHM,
				$username,
				$PASSWORD_GENERATOR_SECRET,
				false);
		$correct_password = substr($correct_password, 0, $PASSWORD_GENERATOR_LENGTH);
    return $correct_password;
  }

  /** 
   * Get header Authorization
   * */
  function getAuthorizationHeader(){
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    }
    else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        //print_r($requestHeaders);
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
  }

  /**
  * get access token from header
  * */
  function getBearerToken() {
    $headers = getAuthorizationHeader();
    // HEADER: Get the access token from the header
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
  }

  function getUsernameFromToken() {
    global $JWT_KEY, $JWT_ALGORITHM;

    $token = getBearerToken();
    if ($token) {
      try {
        $decoded = JWT::decode($token, new Key($JWT_KEY, $JWT_ALGORITHM));
        return $decoded->sub;
      } catch (ExpiredException $e) {
        return null;
      }
    } else {
      return null;
    }
  }

  // https://stackoverflow.com/questions/8719276/cross-origin-request-headerscors-with-php-headers
  function cors() {
    // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
        // you want to allow, and if so:
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }
    
    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            // may also be using PUT, PATCH, HEAD etc
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    
        exit(0);
    }
  }

  function getCurrentQuestionIndex($conn, $assignment_url) {
    $sql = $conn->prepare("SELECT question_index
        FROM sub_assignment_control
        WHERE assignment_url = ?
        AND is_open = TRUE
        AND expiration_time > NOW();");
    $sql->bind_param("s", $assignment_url);
    $sql->execute();
    $result = $sql->get_result();
    if (($result) && ($result->num_rows !== 0)) {
      $row = $result->fetch_assoc();
      return $row["question_index"];
    } else {
      return NULL;
    }
  }

  function getNextQuestionIndex($conn, $assignment_url) {
    $sql = $conn->prepare("SELECT question_index
        FROM sub_assignment_control
        WHERE assignment_url = ?");
    $sql->bind_param("s", $assignment_url);
    $sql->execute();
    $result = $sql->get_result();
    if (($result) && ($result->num_rows !== 0)) {
      $row = $result->fetch_assoc();
      return $row["question_index"] + 1;
    } else {
      return 0;
    }
  }

  function openNextQuestion($conn, $assignment_url) {
    global $JWT_DURATION;
    $nextQuestion = getNextQuestionIndex($conn, $assignment_url);
    if ($nextQuestion != 0) {
      $sql = $conn->prepare("UPDATE sub_assignment_control
          SET question_index = $nextQuestion,
          is_open = TRUE,
          expiration_time = DATE_ADD(NOW(), INTERVAL $JWT_DURATION SECOND)
          WHERE assignment_url = ?");
      $sql->bind_param("s", $assignment_url);
      $sql->execute();
    } else {
      $sql = $conn->prepare("
          INSERT INTO sub_assignment_control (assignment_url, question_index, expiration_time, is_open)
          VALUES (?, 0, DATE_ADD(NOW(), INTERVAL $JWT_DURATION SECOND), TRUE);");
      $sql->bind_param("s", $assignment_url);
      $sql->execute();
    }
  }

  function closeCurrentQuestion($conn, $assignment_url) {
    $sql = $conn->prepare("
        UPDATE sub_assignment_control
        SET is_open = FALSE
        WHERE assignment_url = ?");
    $sql->bind_param("s", $assignment_url);
    $sql->execute();
  }

  function addSubmission($conn, $timestamp, $assignment_url, $username, $single_answer, $question_index, $question_type, $submission_type) {
    $sql = $conn->prepare("INSERT INTO sub_submissions (
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
  }
?>