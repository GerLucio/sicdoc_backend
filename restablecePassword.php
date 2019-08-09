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
	
	$password_cambio = false;
	
	$usuario = new Usuario();
	$usuario->id_usuario = $usuario_request['usuario']['ID_USUARIO'];
	$usuario->nombre = trim($usuario_request['usuario']['NOMBRE'])." ".trim($usuario_request['usuario']['APELLIDO']);
	$usuario->correo = trim($usuario_request['usuario']['CORREO']);

	if($usuario->id_usuario == null){
		$respuesta = array('Error' => 'Error el usuario no existe');
		echo json_encode($respuesta);
		exit;
	}

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$pass_temp = $usuario->generaPassword();
	$usuario->setPassword($pass_temp);
	
	$consulta_password = "UPDATE USUARIOS SET estado = 2, password = :password  WHERE id_usuario = :id_usuario";
	
	$ejecuta_consulta_password = oci_parse($conexion, $consulta_password);
	oci_bind_by_name($ejecuta_consulta_password, ':password', $usuario->password);
	oci_bind_by_name($ejecuta_consulta_password, ':id_usuario', $usuario->id_usuario);
	$resultado = oci_execute($ejecuta_consulta_password);
	
	if($resultado){
		$password_cambio = true;
		$respuesta = array('Exito' => 'Se ha restablecido la contraseña');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_password);
		//$respuesta = array('Error' => $e['message']);
		$respuesta = array('Error' => 'No se pudo cambiar la contraseña');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_password);
	oci_close($conexion);
	
	if($password_cambio){
		$mail = new PHPMailer(true); // Declaramos un nuevo correo, el parametro true significa que mostrara excepciones y errores. 		  
		try { 
			$mail->IsSMTP(); // Se especifica a la clase que se utilizará SMTP 
		
			$correo_emisor="upev.sgc@gmail.com";
			$nombre_emisor=utf8_decode("Coordinación SGC-UPEV");
			$datos = new CifraPass();

			$contrasena = $datos->descifra($datos->password_correo);
			$mail->SMTPDebug  = 3;
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
		  $mail->Subject = utf8_decode("Restablece tu contraseña"); 
		  //Mensaje alternativo en caso que el destinatario no pueda abrir correos HTML 
		  $mail->AltBody = 'Para ver el mensaje necesita un cliente de correo compatible con HTML.'; 
		  //El cuerpo del mensaje, puede ser con etiquetas HTML 
		  $mail->isHTML(true);
		  $mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
			Estimado(a) <strong>".$usuario->nombre."</strong> buen día.<br><br>
			<p>De acuerdo a su solicitud se le asigno la siguiente contraseña temporal <strong>".$pass_temp."</strong>, 
			es necesario que ingreses al sistema para cambiarla (recuerda que una contraseña segura debe tener mínimo 8 caracteres, 
			y al menos 1 letra mayúscula y 1 numero).</p>
			<p>Si no solicitaste un cambio de contraseña, comunícate con el Departamento de Evaluación y Gestión de la Calidad.</p>
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