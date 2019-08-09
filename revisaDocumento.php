<?PHP
	error_reporting(0);
	
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
	$documento->id_revision = trim($usuario_request['documento']['ID_REVISION']);
	$documento->no_revision = trim($usuario_request['documento']['NO_REVISION'])-1;
	$documento->id_documento = trim($usuario_request['documento']['ID_DOCUMENTO']);
	$documento->ruta = trim($usuario_request['documento']['RUTA']);
	$documento->nombre = trim($usuario_request['documento']['NOMBRE']);
	$documento->codigo = trim($usuario_request['documento']['CODIGO']);
	$documento->tipo = trim($usuario_request['documento']['TIPO']);
	
	if($accion == 'declinar'){
		$documento->observacion = trim($usuario_request['documento']['OBSERVACION']);
		$consulta_modifica = "UPDATE revisiones set observacion = :observacion WHERE id_revision = :id_revision";
		
		$ejecuta_consulta_modifica = oci_parse($conexion, $consulta_modifica);
		oci_bind_by_name($ejecuta_consulta_modifica, ':observacion', $documento->observacion);
		oci_bind_by_name($ejecuta_consulta_modifica, ':id_revision', $documento->id_revision);
	}
	else if($accion == 'aprobar'){
		$consulta_modifica = "BEGIN
							UPDATE revisiones set vigente = 1, fecha_revision = SYSDATE, observacion = null, ruta = :ruta WHERE id_revision = :id_revision;
							UPDATE revisiones set vigente = 0 WHERE id_revision != :id_revision and documento = :id_documento;
												COMMIT;
							EXCEPTION
								WHEN OTHERS THEN
								  ROLLBACK;
							END;";
		
		$ejecuta_consulta_modifica = oci_parse($conexion, $consulta_modifica);
		oci_bind_by_name($ejecuta_consulta_modifica, ':id_revision', $documento->id_revision);
		oci_bind_by_name($ejecuta_consulta_modifica, ':ruta', $documento->ruta);
		oci_bind_by_name($ejecuta_consulta_modifica, ':id_documento', $documento->id_documento);
		
	}
	
	$resultado = oci_execute($ejecuta_consulta_modifica);
	
	if($resultado){
		$correcto = true;
		$consulta_usuario = "SELECT a.nombre ||' ' || a.apellido as nombre, a.correo from usuarios a
								LEFT JOIN departamentos b ON b.lider = a.id_usuario
								LEFT JOIN procesos c ON c.departamento = b.id_depto
								LEFT JOIN documentos d ON d.proceso = c.id_proceso
								LEFT JOIN revisiones e ON e.documento = d.id_documento
								WHERE e.id_revision = :id_revision";
		$ejecuta_consulta_usuario = oci_parse($conexion, $consulta_usuario);
		oci_bind_by_name($ejecuta_consulta_usuario, ':id_revision', $documento->id_revision);
		oci_execute($ejecuta_consulta_usuario);
		if($row = oci_fetch_array($ejecuta_consulta_usuario, OCI_ASSOC+OCI_RETURN_NULLS)){
			$nombre = $row['NOMBRE'];
			$correo = $row['CORREO'];
		}
		oci_free_statement($ejecuta_consulta_usuario);
		$respuesta = array('Exito' => 'Cambios guardados correctamente');
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
			
			$correo_destino=$correo;      //Correo de quien recibe 
			$nombre_destino=utf8_decode($nombre);                //Nombre de quien recibe 
			//A que dirección se puede responder el correo 
			$mail->AddReplyTo($correo_emisor, $nombre_emisor); 
			//La direccion a donde mandamos el correo 
			$mail->AddAddress($correo_destino, $nombre_destino); 
			//De parte de quien es el correo 
			$mail->SetFrom($correo_emisor, $nombre_emisor); 
			  
			if($accion == 'declinar'){
				$mail->Subject = utf8_decode("Observaciones del documento ".$documento->nombre." en el SGC"); 
				$mail->isHTML(true);
				$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
				  Estimado(a) <strong>".$nombre."</strong> buen día.<br><br>
					<p>En relación al cambio de revisión del documento <strong>".$documento->codigo." ".$documento->nombre."</strong> se notifica que no fue aprobado dentro del 
					Sistema Interno de Control de documentos (SICDOC), debido a las siguientes observaciones:
					<strong>".$documento->observacion."</strong>.</p>
					<p>Se solicita su valioso apoyo para hacer las modificaciones pertinentes al documento y 
					realizar de nuevo la solicitud dentro del sistema.</p><br><br>
					<p><img src='cid:firma'></p>
					<p>Mensaje enviado de forma automática.</p>");
			}
			else if($accion == 'aprobar'){	
				/*$consulta_coordinador = "SELECT
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
				oci_free_statement($ejecuta_consulta_coordinador);*/
				
				$consulta_usuarios = "SELECT nombre ||' '|| apellido as nombre,
						correo 
						FROM v_usuarios_activos
						WHERE id_departamento = (SELECT DISTINCT(id_depto) from v_documentos_activos WHERE id_documento = :id_documento)";
				$ejecuta_consulta_usuarios = oci_parse($conexion, $consulta_usuarios);
				oci_bind_by_name($ejecuta_consulta_usuarios, ':id_documento', $documento->id_documento);
				oci_execute($ejecuta_consulta_usuarios);
				while($row = oci_fetch_array($ejecuta_consulta_usuarios, OCI_ASSOC+OCI_RETURN_NULLS)){
					$usuarios[] = $row;
				}
				oci_free_statement($ejecuta_consulta_usuarios);
				$mail->Subject = utf8_decode("Cambio de revisión del documento ".$documento->codigo." en el SGC"); 
				$mail->isHTML(true);
				$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
				  Estimado(a) <strong>".$nombre."</strong> buen día.<br><br>
					<p>En relación a su solicitud del cambio de revisión del documento <strong>".$documento->codigo." ".$documento->nombre."</strong> 
					en el Sistema Interno de Control de documentos (SICDOC), se notifica que ha sido registrada satisfactoriamente en el sistema 
					con la siguiente revisión <strong>R".$documento->no_revision."</strong>.</p><br><br>
					<p><img src='cid:firma'></p>
					<p>Mensaje enviado de forma automática.</p>"); 
				//$mail->AddCC($coordinador->correo, $coordinador->nombre);
				foreach($usuarios as $usuario){
					$mail->AddCC($usuario['CORREO'], utf8_decode($usuario['NOMBRE']));
				}
			}
			  //Mensaje alternativo en caso que el destinatario no pueda abrir correos HTML 
			  $mail->AltBody = 'Para ver el mensaje necesita un cliente de correo compatible con HTML.'; 
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