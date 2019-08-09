<?PHP
	error_reporting(0);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_usuario.php';
	include 'conexion/conexion.php';
	include 'clases/clase_token.php';
	//include ("PHPMailer/class.phpmailer.php"); 
	//include ("PHPMailer/class.smtp.php");
	include 'clases/Clase_Cifra.php';
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;	
	include ("PHPMailer-master/src/Exception.php"); 
	include ("PHPMailer-master/src/PHPMailer.php"); 
	include ("PHPMailer-master/src/SMTP.php"); 
	
	
	$usuario_request = json_decode(file_get_contents("php://input"), true);
	
	$tkn = $usuario_request['tkn'];
	$token = new Token();
	$tkn_valido = $token->validaToken($tkn);
	
	if(!$tkn_valido){
		$respuesta = array('ErrorToken' => 'Token no válido');
		echo json_encode($respuesta);
		exit;
	}
	
	$alta_correcta = false;
	$usuario = new Usuario();
	$url = trim($usuario_request['url']);
	$usuario->nombre = trim($usuario_request['usuario']['nombre']);
	$usuario->apellido = trim($usuario_request['usuario']['apellido']);
	$usuario->puesto = trim($usuario_request['usuario']['puesto']);
	$usuario->correo = trim($usuario_request['usuario']['correo']);
	$usuario->departamento = trim($usuario_request['usuario']['departamento']);
	$usuario->rol = trim($usuario_request['usuario']['rol']);
	$pass_temp = $usuario->generaPassword();
	$usuario->setPassword($pass_temp);
	

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_inserta = "insert into usuarios values(
			(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
			:nombre,
			:apellido,
			:puesto,
			:correo,
			:password,
			:departamento,
			:rol,
			2
			)";
	
	$ejecuta_consulta_inserta = oci_parse($conexion, $consulta_inserta);
	oci_bind_by_name($ejecuta_consulta_inserta, ':nombre', $usuario->nombre);
	oci_bind_by_name($ejecuta_consulta_inserta, ':apellido', $usuario->apellido);
	oci_bind_by_name($ejecuta_consulta_inserta, ':puesto', $usuario->puesto);
	oci_bind_by_name($ejecuta_consulta_inserta, ':correo', $usuario->correo);
	oci_bind_by_name($ejecuta_consulta_inserta, ':password', $usuario->password);
	oci_bind_by_name($ejecuta_consulta_inserta, ':departamento', $usuario->departamento);
	oci_bind_by_name($ejecuta_consulta_inserta, ':rol', $usuario->rol);
	$inserta = oci_execute($ejecuta_consulta_inserta);
	
	if($inserta){
		$alta_correcta = true;
		$respuesta = array('Exito' => 'Usuario creado correctamente');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_inserta);
		$mensaje = explode('_', $e['message']);
		$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		//$respuesta = array('Error' => 'Error al crear usuario');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_inserta);
	oci_close($conexion);
	
	if($alta_correcta){
		$mail = new PHPMailer(true); // Declaramos un nuevo correo, el parametro true significa que mostrara excepciones y errores. 
  
		
		  
		try {
			$mail->IsSMTP(); // Se especifica a la clase que se utilizará SMTP 
		
			$correo_emisor="upev.sgc@gmail.com";
			$nombre_emisor=utf8_decode("Coordinación SGC-UPEV");
			$datos = new CifraPass();

			$contrasena = $datos->descifra($datos->password_correo);
			$mail->SMTPDebug  = 2;
			$mail->SMTPAuth   = true;
			$mail->SMTPSecure = "tls";
			//$mail->Host       = "correo.ipn.mx";
			$mail->Host       = "smtp.gmail.com";
			$mail->Port       = 587;
			$mail->Username   = $correo_emisor; 
			$mail->Password   = $contrasena;
			$mail->AddEmbeddedImage('img/firma_sicdoc.jpg', 'firma');
			
			$correo_destino=$usuario->correo;      //Correo de quien recibe 
			$nombre_destino=utf8_decode($usuario->nombre);                //Nombre de quien recibe 
			//A que dirección se puede responder el correo 
			$mail->AddReplyTo($correo_emisor, $nombre_emisor); 
			//La direccion a donde mandamos el correo 
			$mail->AddAddress($correo_destino, $nombre_destino); 
			//De parte de quien es el correo 
			$mail->SetFrom($correo_emisor, $nombre_emisor); 
			//Asunto del correo 
			$mail->Subject = utf8_decode("Registro al Sistema Interno de Control de Documentos - SICDOC"); 
			//Mensaje alternativo en caso que el destinatario no pueda abrir correos HTML 
			$mail->AltBody = 'Para ver el mensaje necesita un cliente de correo compatible con HTML.'; 
			$mail->isHTML(true);
			  $mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
				Apreciable <strong>".$usuario->nombre." ".$usuario->apellido."</strong>.<br><br>
				<p>Bienvenido al Sistema Interno de Control de documentos (SICDOC) del SGC.</p>
				<p>Has sido registrado satisfactoriamente en el sistema, podrás acceder entrando al Link <strong>".$url."</strong> 
				con tu correo electrónico institucional y la siguiente contraseña temporal <strong>".$pass_temp."</strong>.</p>
				<p>Para una mejor seguridad de tu cuenta se recomienda ingresar al sistema y cambiar la contraseña, 
				recuerda que una contraseña segura debe tener mínimo 8 caracteres, y al menos 1 letra mayúscula y 1 numero.</p>
				<p>Cualquier duda comunicarse con el Departamento de Evaluación y Gestión de la Calidad.</p>
				<br><br>
				<p><img src='cid:firma'></p>
				<p>Mensaje enviado de forma automática.</p>");
			//Enviamos el correo
			$mail->Send(); 
			//echo "El mensaje se ha enviado correctamente"; 
		} catch (phpmailerException $e) { 
		  //echo $e->errorMessage(); //Errores de PhpMailer 
		} catch (Exception $e) { 
		  //echo $e->getMessage(); //Errores de cualquier otra cosa. 
		}
	}
	exit;
	
?>