<?PHP
	error_reporting(0);
    //error_reporting(E_ALL);
    //ini_set('display_errors', TRUE);
    //ini_set('display_startup_errors', TRUE);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_documento.php';
	include 'conexion/conexion.php';
	include 'clases/clase_token.php';
	include 'clases/clase_usuario.php';
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
	
	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$correcto = false;
	$accion = trim($usuario_request['accion']);
	
	$documento = new Documento();
	$documento->id_documento = trim($usuario_request['documento']['ID_DOCUMENTO']);
	$documento->proceso = trim($usuario_request['documento']['ID_PROCESO']);
	$documento->nombre_proceso = trim($usuario_request['documento']['PROCESO']);
	$documento->nombre = trim($usuario_request['documento']['NOMBRE']);
	$documento->codigo = trim($usuario_request['documento']['CODIGO']);
	
	if($accion == 'declinar'){
		$documento->observacion = trim($usuario_request['documento']['OBSERVACION']);
		$consulta_modifica = "UPDATE documentos set observacion = :observacion, fecha_fin = null WHERE id_documento = :id_documento";
		
		$ejecuta_consulta_modifica = oci_parse($conexion, $consulta_modifica);
		oci_bind_by_name($ejecuta_consulta_modifica, ':observacion', $documento->observacion);
		oci_bind_by_name($ejecuta_consulta_modifica, ':id_documento', $documento->id_documento);
	}
	else if($accion == 'aprobar'){
		$consulta_modifica = "UPDATE documentos SET estado = 0, fecha_fin = SYSDATE, observacion = null WHERE id_documento = :id_documento";
		$ejecuta_consulta_modifica = oci_parse($conexion, $consulta_modifica);
		oci_bind_by_name($ejecuta_consulta_modifica, ':id_documento', $documento->id_documento);
	}
	$resultado = oci_execute($ejecuta_consulta_modifica);
	
	if($resultado){
		$correcto = true;
		$consulta_usuario = "SELECT a.nombre ||' ' || a.apellido as nombre, a.correo from usuarios a
								LEFT JOIN departamentos b ON b.lider = a.id_usuario
								LEFT JOIN procesos c ON c.departamento = b.id_depto
								LEFT JOIN documentos d ON d.proceso = c.id_proceso
								WHERE id_documento = :id_documento";
		$ejecuta_consulta_usuario = oci_parse($conexion, $consulta_usuario);
		oci_bind_by_name($ejecuta_consulta_usuario, ':id_documento', $documento->id_documento);
		oci_execute($ejecuta_consulta_usuario);
		if($row = oci_fetch_array($ejecuta_consulta_usuario, OCI_ASSOC+OCI_RETURN_NULLS)){
			$nombre = $row['NOMBRE'];
			$correo = $row['CORREO'];
		}
		oci_free_statement($ejecuta_consulta_usuario);
		if($accion == 'declinar')
			$respuesta = array('Exito' => 'Baja de documento rechazada');
		else if($accion == 'aprobar')
			$respuesta = array('Exito' => 'Baja de documento correcta');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_modifica);
		//$respuesta = array('Error' => $e['message']);
		$respuesta = array('Error' => 'Error al modificar documento');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_modifica);
	
	if($correcto){
		$mail = new PHPMailer(true); // Declaramos un nuevo correo, el parametro true significa que mostrara excepciones y errores. 
  
		
		  
		try {
		
		$mail->IsSMTP(); // Se especifica a la clase que se utilizará SMTP 	
			
			$correo_emisor="dev.sgc.sicdoc@gmail.com";
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
			
			$correo_destino=$correo;      //Correo de quien recibe 
			$nombre_destino=utf8_decode($nombre);                //Nombre de quien recibe 
			//A que dirección se puede responder el correo 
			$mail->AddReplyTo($correo_emisor, $nombre_emisor); 
			//La direccion a donde mandamos el correo 
			$mail->AddAddress($correo_destino, $nombre_destino); 
			//De parte de quien es el correo 
			$mail->SetFrom($correo_emisor, $nombre_emisor); 
			$mail->AltBody = 'Para ver el mensaje necesita un cliente de correo compatible con HTML.'; 
		  
			if($accion == 'declinar'){
				$mail->Subject = utf8_decode("Observaciones del documento ".$documento->nombre." en el SGC"); 
				$mail->isHTML(true);
				$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
				  Estimado(a) <strong>".$nombre."</strong> buen día.<br><br>
					<p>En relación la solicitud de baja del documento <strong>".$documento->codigo." ".$documento->nombre."</strong> 
					se notifica que no fue aprobada dentro del Sistema Interno de Control de documentos (SICDOC), debido a las siguientes observaciones:
					<strong>".$documento->observacion."</strong>.</p><br><br>
					<p><img src='cid:firma'></p>
					<p>Mensaje enviado de forma automática.</p>");
			}
			else if($accion == 'aprobar'){	
				$consulta_coordinador = "SELECT
						nombre ||' '|| apellido as nombre,
						correo
					FROM v_usuarios_activos
					WHERE id_rol = 1";
				$ejecuta_consulta_coordinador = oci_parse($conexion, $consulta_coordinador);
				oci_execute($ejecuta_consulta_coordinador);
				while($row = oci_fetch_array($ejecuta_consulta_coordinador, OCI_ASSOC+OCI_RETURN_NULLS)){
					$coordinador = new Usuario();
					$coordinador->nombre = $row['NOMBRE'];
					$coordinador->correo = $row['CORREO'];
				}
				oci_free_statement($ejecuta_consulta_coordinador);
				
				$consulta_usuarios = "SELECT nombre ||' '|| apellido as nombre,
						correo 
						FROM v_usuarios_activos
						WHERE id_departamento = (SELECT DISTINCT(departamento) from procesos WHERE id_proceso = :proceso)";
				$ejecuta_consulta_usuarios = oci_parse($conexion, $consulta_usuarios);
				oci_bind_by_name($ejecuta_consulta_usuarios, ':proceso', $documento->proceso);
				oci_execute($ejecuta_consulta_usuarios);
				while($row = oci_fetch_array($ejecuta_consulta_usuarios, OCI_ASSOC+OCI_RETURN_NULLS)){
					$usuarios[] = $row;
				}
				oci_free_statement($ejecuta_consulta_usuarios);
				$mail->Subject = utf8_decode("Baja del documento ".$documento->nombre." en el SGC"); 
				$mail->isHTML(true);
				$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
				  Estimado(a) <strong>".$nombre."</strong> buen día.<br><br>
					<p>En relación la solicitud de baja del documento <strong>".$documento->codigo." ".$documento->nombre."</strong> 
					al proceso <strong>".$documento->nombre_proceso."</strong>, se le notifica que ha sido dado de baja correctamente en el 
					Sistema Interno de Control de documentos (SICDOC), así mismo, 
					solicitamos su valioso apoyo a fin de actualizar su lista de control de registros de su proceso <strong>SGC-05</strong>.</p><br><br>
					<p><img src='cid:firma'></p>
					<p>Mensaje enviado de forma automática.</p>");
				$mail->AddCC($coordinador->correo, $coordinador->nombre);
				foreach($usuarios as $usuario){
					$mail->AddCC($usuario['CORREO'], utf8_decode($usuario['NOMBRE']));
				}
			}
			  //El cuerpo del mensaje, puede ser con etiquetas HTML 
			  
			  //Enviamos el correo
			  $mail->Send(); 
			  //echo "El mensaje se ha enviado correctamente"; 
		} catch (phpmailerException $e) { 
		  //echo $e->errorMessage(); //Errores de PhpMailer 
		} catch (Exception $e) { 
		  //echo $e->getMessage(); //Errores de cualquier otra cosa. 
		}
	}
	
	oci_close($conexion);
	exit;
	
?>