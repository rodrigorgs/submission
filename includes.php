<?php
  if (php_sapi_name() != "cli") {
    cors();
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

  JWT::$leeway = $JWT_LEEWAY ?? 15 * 60; // $leeway in seconds

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
    return @$_GET['token'] ?? @$_POST['token'];
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

  // https://stackoverflow.com/questions/1049401/how-to-select-content-type-from-http-accept-header-in-php
  function getBestSupportedMimeType($mimeTypes = null) {
    // Values will be stored in this array
    $AcceptTypes = Array ();

    // Accept header is case insensitive, and whitespace isn’t important
    $accept = strtolower(str_replace(' ', '', $_SERVER['HTTP_ACCEPT']));
    // divide it into parts in the place of a ","
    $accept = explode(',', $accept);
    foreach ($accept as $a) {
        // the default quality is 1.
        $q = 1;
        // check if there is a different quality
        if (strpos($a, ';q=')) {
            // divide "mime/type;q=X" into two parts: "mime/type" i "X"
            list($a, $q) = explode(';q=', $a);
        }
        // mime-type $a is accepted with the quality $q
        // WARNING: $q == 0 means, that mime-type isn’t supported!
        $AcceptTypes[$a] = $q;
    }
    arsort($AcceptTypes);

    // if no parameter was passed, just return parsed data
    if (!$mimeTypes) return $AcceptTypes;

    $mimeTypes = array_map('strtolower', (array)$mimeTypes);

    // let’s check our supported types:
    foreach ($AcceptTypes as $mime => $q) {
       if ($q && in_array($mime, $mimeTypes)) return $mime;
    }
    // no mime-type found
    return null;
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

  function getAnswerCount($conn, $assignment_url) {
    $sql = $conn->prepare("
    SELECT answer, COUNT(*) AS count
    FROM `sub_submissions`
    WHERE `assignment_url` = ?
    AND `question_index` = (
      SELECT MAX(question_index) FROM sub_submissions
      WHERE `assignment_url` = ?)
    GROUP BY answer
    ORDER BY answer;
    ");
    $sql->bind_param("ss", $assignment_url, $assignment_url);
    $sql->execute();
    $result = $sql->get_result();

    $rows = array();
    while($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }

    return $rows;
  }
?>