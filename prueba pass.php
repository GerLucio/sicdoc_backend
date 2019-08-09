<?PHP
	$pass = "12345678";
	$pass = base64_encode($pass);
	$options = ['memory_cost' => 1024, 'time_cost' => 2, 'threads' => 2];
	$pass_hash = password_hash($pass, PASSWORD_ARGON2I, $options);
	var_dump(base64_encode($pass_hash));
	$login = password_verify(base64_encode('12345678'), $pass_hash);
	//$login = password_verify(base64_encode('prueba'), base64_decode('JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRUVFJ6T0hKeVdIVk9Sa3cyTVVGNVp3JE1Zc2RhYnFhZHBVcXN5MUc5b2NkSVRtKzJ4em5tQXJ3Ym1zZHhDM21mbms='));
	var_dump($login);
	//ng build --prod --base-href=/sicdoc/
?>