<?php
	use Firebase\JWT\JWT;
	use Firebase\JWT\Key;

	require_once __DIR__ . '/includes.php';

	$data = json_decode(file_get_contents('php://input'), true);

	$username = $data["username"];
	$password = $data["password"];

	// Determine correct password using hash
	if ($username == $ADMIN_USERNAME) {
		$correct_password = $ADMIN_PASSWORD;
	} else {
		$correct_password = hash_hmac($PASSWORD_GENERATOR_ALGORITHM,
				$username,
				$PASSWORD_GENERATOR_SECRET,
				false);
		$correct_password = substr($correct_password, 0, $PASSWORD_GENERATOR_LENGTH);
	}

	// Verify password and return JWT token
	if ($password == $correct_password) {
		$payload = array(
			"sub" => $username,
			"iat" => time(),
			"exp" => time() + $JWT_DURATION
		);
		$jwt = JWT::encode($payload, $JWT_KEY, $JWT_ALGORITHM);
		print_r($jwt);
	} else {
		http_response_code(401);
		echo "Invalid username or password.";
	}
?>