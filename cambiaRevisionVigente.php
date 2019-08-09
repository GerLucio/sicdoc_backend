<?PHP
	error_reporting(0);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_revision.php';
	include 'conexion/conexion.php';
	include 'clases/clase_token.php';
	include 'clases/clase_proceso.php';
	include 'clases/clase_usuario.php';
	include 'clases/clase_documento.php';
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
	
	$revision = new Revision();
	$revision->id_revision = $usuario_request['revision']['ID_REVISION'];
	$revision->no_revision = ($usuario_request['revision']['NO_REVISION'])-1;
	$revision->id_documento = $usuario_request['revision']['ID_DOCUMENTO'];
	$revision->documento = $usuario_request['documento']['NOMBRE'];

	$consulta_modifica = "BEGIN
							UPDATE revisiones set vigente = 0 WHERE documento = :id_documento;
							UPDATE revisiones set vigente = 1 WHERE id_revision = :id_revision;
												COMMIT;
							EXCEPTION
								WHEN OTHERS THEN
								  ROLLBACK;
							END;";
		
	$ejecuta_consulta_modifica = oci_parse($conexion, $consulta_modifica);
	oci_bind_by_name($ejecuta_consulta_modifica, ':id_revision', $revision->id_revision);
	oci_bind_by_name($ejecuta_consulta_modifica, ':id_documento', $revision->id_documento);

	
	$resultado = oci_execute($ejecuta_consulta_modifica);
	
	if($resultado){
		$correcto = true;
		$respuesta = array('Exito' => 'La revisión vigente se ha establecido correctamente');
		echo json_encode($respuesta);	
	}
	else{
		$e = oci_error($ejecuta_consulta_modifica);
		//$respuesta = array('Error' => $e['message']);
		$respuesta = array('Error' => 'Error al cambiar revisión vigente');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_modifica);
	
	if($correcto){
		$consulta_proceso = "select b.nombre, b.id_proceso, a.nombre as documento, a.codigo from documentos a JOIN procesos b ON a.proceso = b.id_proceso WHERE a.id_documento = :id_documento";
		$ejecuta_consulta_proceso = oci_parse($conexion, $consulta_proceso);
		oci_bind_by_name($ejecuta_consulta_proceso, ":id_documento", $revision->id_documento);
		oci_execute($ejecuta_consulta_proceso);
		while($row = oci_fetch_array($ejecuta_consulta_proceso, OCI_ASSOC+OCI_RETURN_NULLS)){
			$proceso = new Proceso();
			$documento = new Documento();
			$proceso->id_proceso = $row['ID_PROCESO'];
			$proceso->nombre = $row['NOMBRE'];
			$documento->nombre = $row['DOCUMENTO'];
			$documento->codigo = $row['CODIGO'];
		}
		oci_free_statement($ejecuta_consulta_proceso);
		
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

		  //A que dirección se puede responder el correo 
		  $mail->AddReplyTo($correo_emisor, $nombre_emisor); 
		  //La direccion a donde mandamos el correo 
		  //De parte de quien es el correo 
		  $mail->SetFrom($correo_emisor, $nombre_emisor); 
		  //Asunto del correo 
		  //Mensaje alternativo en caso que el destinatario no pueda abrir correos HTML 
		  $mail->AltBody = 'Para ver el mensaje necesita un cliente de correo compatible con HTML.'; 
		  $mail->isHTML(true); 
		  
		  $consulta_valida_proceso = "select a.nombre from procesos a join departamentos b on a.departamento = b.id_depto where a.id_proceso = :proceso 
				and	UPPER(a.nombre) in(UPPER('Auditoria Interna'), UPPER('Medición, análisis y mejora'), 
				UPPER('Revisión por la Dirección'), UPPER('Coordinación del Sistema de Gestión de la Calidad'))
				and b.nombre = 'Evaluación y Gestión de la Calidad'";
			$ejecuta_consulta_valida_proceso = oci_parse($conexion, $consulta_valida_proceso);
			oci_bind_by_name($ejecuta_consulta_valida_proceso, ':proceso', $proceso->id_proceso);
			oci_execute($ejecuta_consulta_valida_proceso);
			$row = oci_fetch_array($ejecuta_consulta_valida_proceso, OCI_ASSOC+OCI_RETURN_NULLS);
			if($row != false){
				$mail->Subject = utf8_decode("Cambio de revisión del documento SGC <strong>".$documento->codigo."</strong>");
				$consulta_usuarios = "SELECT nombre ||' '|| apellido as nombre,
						correo 
						FROM v_usuarios_activos";
				$ejecuta_consulta_usuarios = oci_parse($conexion, $consulta_usuarios);
				oci_execute($ejecuta_consulta_usuarios);
				while($row = oci_fetch_array($ejecuta_consulta_usuarios, OCI_ASSOC+OCI_RETURN_NULLS)){
					$usuarios[] = $row;
				}
				oci_free_statement($ejecuta_consulta_usuarios);
				
				foreach($usuarios as $usuario){
					//La direccion a donde mandamos el correo 
					$mail->AddAddress($usuario['CORREO']); 
					$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
						Estimado(a) <strong>".$usuario['NOMBRE']."</strong> buen día.<br><br>
						<p>Le notificamos que se actualizo el documento del SGC <strong>".$documento->codigo." ".$documento->nombre."</strong> 
						a la revisión <strong>R".$revision->no_revision."</strong> en el Sistema Interno de Control de Documentos (SICDOC), así mismo, 
						solicitamos su valioso apoyo a fin de actualizar su lista de control de registros de su proceso <strong>SGC-05</strong>.</p><br><br>
						<p><img src='cid:firma'></p>
						<p>Mensaje enviado de forma automática.</p>");
						//Enviamos el correo
						$mail->Send(); 
						$mail->ClearAllRecipients();
				}
			}
			else{
				$consulta_usuarios = "SELECT nombre ||' '|| apellido as nombre,
						correo 
						FROM v_usuarios_activos
						WHERE id_departamento = (SELECT DISTINCT(id_depto) from v_documentos_activos WHERE id_documento = :id_documento)";
				$ejecuta_consulta_usuarios = oci_parse($conexion, $consulta_usuarios);
				oci_bind_by_name($ejecuta_consulta_usuarios, ':id_documento', $revision->id_documento);
				oci_execute($ejecuta_consulta_usuarios);
				while($row = oci_fetch_array($ejecuta_consulta_usuarios, OCI_ASSOC+OCI_RETURN_NULLS)){
					$usuarios[] = $row;
				}
				oci_free_statement($ejecuta_consulta_usuarios);
				
				$consulta_usuario = "SELECT a.nombre ||' ' || a.apellido as nombre, a.correo from usuarios a
										LEFT JOIN departamentos b ON b.lider = a.id_usuario
										LEFT JOIN procesos c ON c.departamento = b.id_depto
										LEFT JOIN documentos d ON d.proceso = c.id_proceso
										WHERE d.id_documento = :id_documento";
				$ejecuta_consulta_usuario = oci_parse($conexion, $consulta_usuario);
				oci_bind_by_name($ejecuta_consulta_usuario, ':id_documento', $revision->id_documento);
				oci_execute($ejecuta_consulta_usuario);
				if($row = oci_fetch_array($ejecuta_consulta_usuario, OCI_ASSOC+OCI_RETURN_NULLS)){
					$nombre = $row['NOMBRE'];
					$correo = $row['CORREO'];
				}
				oci_free_statement($ejecuta_consulta_usuario);
				$mail->Subject = utf8_decode("Cambio de revisión del documento <strong>".$documento->codigo."</strong>");
				$mail->AddAddress($correo, utf8_decode($nombre));
				$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
						Estimado(a) <strong>".$nombre."</strong> buen día.<br><br>
						<p>Le notificamos que se actualizo el documento del <strong>".$documento->codigo." ".$documento->nombre."</strong> 
						a la revisión <strong>R".$revision->no_revision."</strong> en el Sistema Interno de Control de Documentos (SICDOC), así mismo, 
						solicitamos su valioso apoyo a fin de actualizar su lista de control de registros de su proceso.</p><br><br>
						<p><img src='cid:firma'></p>
						<p>Mensaje enviado de forma automática.</p>");
				foreach($usuarios as $usuario){
					$mail->AddCC($usuario['CORREO'], utf8_decode($usuario['NOMBRE']));
				}
				$mail->Send();
			} 
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