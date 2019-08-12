<?PHP
	error_reporting(0);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	//header('content-type: application/json;');
	
	include 'clases/clase_documento.php';
	include 'conexion/conexion.php';
	include 'clases/clase_proceso.php';
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
	
	$depto = $usuario_request['depto'];
	$rol = $usuario_request['rol'];
	
	
	$documento = new Documento();
	$proceso = new Proceso();
	$documento->id_documento = $usuario_request['documento']['ID_DOCUMENTO'];
	$documento->nombre = trim($usuario_request['documento']['NOMBRE']);
	$documento->codigo = trim($usuario_request['documento']['CODIGO']);
	$documento->proceso = trim($usuario_request['documento']['ID_PROCESO']);
	$proceso->nombre = trim($usuario_request['documento']['PROCESO']);
	$correcto = false;

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	if($depto == 'Evaluación y Gestión de la Calidad' &&  $rol == '1'){
		$consulta_elimina = "UPDATE documentos SET estado = 0, fecha_fin = SYSDATE WHERE id_documento = :id_documento";
		$ejecuta_consulta_elimina = oci_parse($conexion, $consulta_elimina);
		oci_bind_by_name($ejecuta_consulta_elimina, ':id_documento', $documento->id_documento);
	}
	else{
		$motivo = $usuario_request['motivo_baja'];
		$consulta_elimina = "UPDATE documentos SET fecha_fin = SYSDATE, observacion = :motivo WHERE id_documento = :id_documento";
		$ejecuta_consulta_elimina = oci_parse($conexion, $consulta_elimina);
		oci_bind_by_name($ejecuta_consulta_elimina, ':id_documento', $documento->id_documento);
		oci_bind_by_name($ejecuta_consulta_elimina, ':motivo', $motivo);
	}
	$resultado = oci_execute($ejecuta_consulta_elimina);
	
	if($resultado){
		$correcto = true;
		if($depto == 'Evaluación y Gestión de la Calidad' &&  $rol == '1'){
			$respuesta = array('Exito' => 'Documento eliminado correctamente');	
		}
		else
			$respuesta = array('Exito' => 'Documento en espera de aprobación para su baja');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_elimina);
		$mensaje = explode('_', $e['message']);
		$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		//$respuesta = array('Error' => 'Error al dar de baja el documento');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_elimina);
	
	
	if($correcto){
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
			
			$correo_destino=$coordinador->correo;      //Correo de quien recibe 
			$nombre_destino=utf8_decode($coordinador->nombre);                //Nombre de quien recibe 
			$mail->AddReplyTo($correo_emisor, $nombre_emisor); 
			//De parte de quien es el correo 
			$mail->SetFrom($correo_emisor, $nombre_emisor); 
			//La direccion a donde mandamos el correo
			//Mensaje alternativo en caso que el destinatario no pueda abrir correos HTML 
			$mail->AltBody = 'Para ver el mensaje necesita un cliente de correo compatible con HTML.'; 			
			if($depto == 'Evaluación y Gestión de la Calidad' &&  $rol == '1'){
				
				$consulta_proceso = "select a.nombre from procesos a join departamentos b on a.departamento = b.id_depto where a.id_proceso = :proceso 
					and	UPPER(a.nombre) in(UPPER('Auditoria Interna'), UPPER('Medición, análisis y mejora'), 
					UPPER('Revisión por la Dirección'), UPPER('Coordinación del Sistema de Gestión de la Calidad'))
					and b.nombre = 'Evaluación y Gestión de la Calidad'";
				$ejecuta_consulta_proceso = oci_parse($conexion, $consulta_proceso);
				oci_bind_by_name($ejecuta_consulta_proceso, ':proceso', $documento->proceso);
				oci_execute($ejecuta_consulta_proceso);
				$row = oci_fetch_array($ejecuta_consulta_proceso, OCI_ASSOC+OCI_RETURN_NULLS);
				if($row != false){
					$consulta_usuarios = "SELECT nombre ||' '|| apellido as nombre,
							correo 
							FROM v_usuarios_activos";
					$ejecuta_consulta_usuarios = oci_parse($conexion, $consulta_usuarios);
					oci_execute($ejecuta_consulta_usuarios);
					while($row = oci_fetch_array($ejecuta_consulta_usuarios, OCI_ASSOC+OCI_RETURN_NULLS)){
						$usuarios[] = $row;
					}
					oci_free_statement($ejecuta_consulta_usuarios);
					$mail->Subject = utf8_decode("Baja del documento ".$documento->codigo." en el SGC");
					//El cuerpo del mensaje, puede ser con etiquetas HTML 
					$mail->isHTML(true);
					foreach($usuarios as $usuario){
						$mail->AddAddress($usuario['CORREO']);
						$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
					  Estimado(a) <strong>".$usuario['NOMBRE']."</strong> buen día.<br><br>
						<p>Le notificamos que se dio de baja el documento del SGC: 
						<strong>".$documento->codigo." ".$documento->nombre."</strong> en el Sistema Interno de Control de Documentos (SICDOC), 
						así mismo, solicitamos su valioso apoyo a fin de actualizar su lista de control de registros <strong>SGC-05</strong>.</p><br><br>
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
							WHERE id_departamento = (SELECT DISTINCT(departamento) from procesos WHERE id_proceso = :proceso)";
					$ejecuta_consulta_usuarios = oci_parse($conexion, $consulta_usuarios);
					oci_bind_by_name($ejecuta_consulta_usuarios, ':proceso', $documento->proceso);
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
					oci_bind_by_name($ejecuta_consulta_usuario, ':id_documento', $documento->id_documento);
					oci_execute($ejecuta_consulta_usuario);
					if($row = oci_fetch_array($ejecuta_consulta_usuario, OCI_ASSOC+OCI_RETURN_NULLS)){
						$nombre = $row['NOMBRE'];
						$correo = $row['CORREO'];
					}
					oci_free_statement($ejecuta_consulta_usuario);
					
					$mail->AddAddress($correo, utf8_decode($nombre)); 
					$mail->Subject = utf8_decode("Baja del documento ".$documento->codigo."");
					$mail->isHTML(true);
					$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
					  Estimado(a) <strong>".$nombre."</strong> buen día.<br><br>
						<p>Le notificamos que se dio de baja el documento: 
						<strong>".$documento->codigo." ".$documento->nombre."</strong> en el Sistema Interno de Control de Documentos (SICDOC), 
						así mismo, solicitamos su valioso apoyo a fin de actualizar su lista de control de registros de su proceso.</p><br><br>
						<p><img src='cid:firma'></p>
						<p>Mensaje enviado de forma automática.</p>");
					foreach($usuarios as $usuario){
						$mail->AddCC($usuario['CORREO'], utf8_decode($usuario['NOMBRE']));
					}
					//Enviamos el correo
					$mail->Send();
				}
			}
			else{
				$mail->AddAddress($correo_destino, $nombre_destino); 
				$mail->Subject = utf8_decode("Solicitud de baja de un documento");
				$mail->isHTML(true);
				$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
				<p>
				El proceso <strong>".$proceso->nombre."</strong> solicita la baja del documento <strong>".$documento->codigo." ".$documento->nombre."</strong>
				presentando el siguiente motivo: <strong>".$motivo."</strong>.</p><br><br>
				<p><img src='cid:firma'></p>
				<p>Mensaje enviado de forma automática.</p>");
				//Enviamos el correo
				$mail->Send();
			}
			$mail->AltBody = 'Para ver el mensaje necesita un cliente de correo compatible con HTML.'; 
		} catch (phpmailerException $e) { 
		  //echo $e->errorMessage(); //Errores de PhpMailer 
		} catch (Exception $e) { 
		  //echo $e->getMessage(); //Errores de cualquier otra cosa. 
		}
	}
	
	oci_close($conexion);

	exit;
	
?>