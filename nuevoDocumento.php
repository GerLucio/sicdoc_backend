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
	include 'clases/clase_proceso.php';
	include 'clases/clase_usuario.php';
	//include ("PHPMailer/class.phpmailer.php"); 
	//include ("PHPMailer/class.smtp.php");
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;	
	include ("PHPMailer-master/src/Exception.php"); 
	include ("PHPMailer-master/src/PHPMailer.php"); 
	include ("PHPMailer-master/src/SMTP.php"); 
	
	
	$documento_request = json_decode(file_get_contents("php://input"), true);
	$tkn = $documento_request['tkn'];
	$token = new Token();
	$tkn_valido = $token->validaToken($tkn);
	
	if(!$tkn_valido){
		$respuesta = array('ErrorToken' => 'Token no válido');
		echo json_encode($respuesta);
		exit;
	}
	
	$url = trim($documento_request['url']);
	$alta_correcta = false;
	
	$documento = new Documento();
	$documento->nombre = trim($documento_request['documento']['nombre']);
	$documento->tipo = trim($documento_request['documento']['id_tipo']);
	$documento->proceso = trim($documento_request['documento']['id_proceso']);
	$documento->ubicacion = trim($documento_request['documento']['ubicacion']);
	
	$ruta = trim($documento_request['nombre_archivo']);
	$responsable = trim($documento_request['responsable']);
	
	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_inserta = "insert into documentos values(
										(SELECT NVL(MAX(id_documento), 0) + 1 FROM documentos),
										:nombre,
										:tipo,
										:proceso,
										'Sin código ' || (SELECT NVL(MAX(id_documento), 0) + 1 FROM documentos),
										SYSDATE,
										null,
										:ubicacion,
										null,
										2
									)";
	
	$ejecuta_consulta_inserta = oci_parse($conexion, $consulta_inserta);
	oci_bind_by_name($ejecuta_consulta_inserta, ':nombre', $documento->nombre);
	oci_bind_by_name($ejecuta_consulta_inserta, ':tipo', $documento->tipo);
	oci_bind_by_name($ejecuta_consulta_inserta, ':proceso', $documento->proceso);
	oci_bind_by_name($ejecuta_consulta_inserta, ':ubicacion', $documento->ubicacion);
	$inserta = oci_execute($ejecuta_consulta_inserta);
	
	if($inserta){
		$consulta_revision = "
		DECLARE num_doc number;
		BEGIN
			SELECT id_documento into num_doc from documentos WHERE rownum = 1 order by id_documento desc;
			
			insert into revisiones values(
				(SELECT NVL(MAX(id_revision), 0) + 1 FROM revisiones),
				(SELECT NVL(MAX(no_revision), 0) + 1 FROM revisiones WHERE documento = num_doc),
				num_doc,
				:responsable,
				SYSDATE,
				1,
				null,
				:ruta
			);
		COMMIT;
		EXCEPTION
			WHEN OTHERS THEN
			  ROLLBACK;
		END;";
		$ejecuta_consulta_revision = oci_parse($conexion, $consulta_revision);
		oci_bind_by_name($ejecuta_consulta_revision, ':responsable', $responsable);
		oci_bind_by_name($ejecuta_consulta_revision, ':ruta', $ruta);
		
		$inserta_revision = oci_execute($ejecuta_consulta_revision);
		
		if($inserta_revision){
			$alta_correcta = true;
			$consulta_proceso = "select nombre from procesos where id_proceso = :id_proceso";
			$ejecuta_consulta_proceso = oci_parse($conexion, $consulta_proceso);
			oci_bind_by_name($ejecuta_consulta_proceso, ":id_proceso", $documento->proceso);
			oci_execute($ejecuta_consulta_proceso);
			while($row = oci_fetch_array($ejecuta_consulta_proceso, OCI_ASSOC+OCI_RETURN_NULLS)){
				$proceso = new Proceso();
				$proceso->id_proceso = $documento->proceso;
				$proceso->nombre = $row['NOMBRE'];
			}
			oci_free_statement($ejecuta_consulta_proceso);
			$respuesta = array('Exito' => 'Documento enviado para su aprobación');
			echo json_encode($respuesta);
		}
		else{
			$e = oci_error($ejecuta_consulta_revision);
			$mensaje = explode('_', $e['message']);
			$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
			//$respuesta = array('Error' => 'Error al crear revisión del documento');
			echo json_encode($respuesta);
		}
		oci_free_statement($ejecuta_consulta_revision);
	}
	else{
		$e = oci_error($ejecuta_consulta_inserta);
		if(substr($e['message'], 0, 9) == 'ORA-00001')
			$respuesta = array('Error' => 'No se puede repetir el nombre del documento');
		else{
			$mensaje = explode('_', $e['message']);
			$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		}
		//$respuesta = array('Error' => 'Error al crear documento');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_inserta);
	
	if($alta_correcta){
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
			$mail->IsSMTP();
			
			//$correo_emisor="upev-sgc@ipn.mx";
			$correo_emisor="dev.sgc.sicdoc@gmail.com";
			$nombre_emisor=utf8_decode("Coordinación SGC-UPEV");
			//$contrasena="calidad";
			$contrasena="calidad%13";
			$mail->SMTPDebug  = 2;
			$mail->SMTPAuth   = true;
			$mail->SMTPSecure = "tls";
			//$mail->Host       = "correo.ipn.mx";
			$mail->Host       = "smtp.gmail.com";
			$mail->Port       = 587;
			$mail->Username   = $correo_emisor; 
			$mail->Password   = $contrasena;
			//$mail->AddEmbeddedImage('img/firma_sicdoc.jpg', 'firma');
			
			$correo_destino=$coordinador->correo; 
			$nombre_destino=utf8_decode($coordinador->nombre);
			//-------------------------------------------------------- 
			$mail->AddReplyTo($correo_emisor, $nombre_emisor); 
			$mail->AddAddress($correo_destino, $nombre_destino); 

			$mail->SetFrom($correo_emisor, $nombre_emisor); 
			  
			$mail->Subject = utf8_decode("Solicitud de registro de documento"); 
			$mail->isHTML(true);
			  $mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
				<p>
				El proceso <strong>".$proceso->nombre."</strong> solicita la asignación de un código de identificación para el documento <strong>".$documento->nombre."</strong>.
				</p><br><br>
				<p>Mensaje enviado de forma automática.</p>"); 
				
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