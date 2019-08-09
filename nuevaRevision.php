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
	
	
	$revision_request = json_decode(file_get_contents("php://input"), true);
	$tkn = $revision_request['tkn'];
	$token = new Token();
	$tkn_valido = $token->validaToken($tkn);

	if(!$tkn_valido){
		$respuesta = array('ErrorToken' => 'Token no válido');
		echo json_encode($respuesta);
		exit;
	}
	
	$revision = new Revision();
	$revision->id_documento = trim($revision_request['revision']['id_documento']);
	$revision->documento = trim($revision_request['revision']['documento']);
	$revision->id_responsable = trim($revision_request['revision']['id_responsable']);
	$revision->ruta = trim($revision_request['nombre_archivo']);
	
	$correcto = false;

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_inserta = "BEGIN
							UPDATE revisiones set vigente = 0 WHERE documento = :id_documento;
							insert into revisiones values(
											(SELECT NVL(MAX(id_revision), 0) + 1 FROM revisiones),
											(SELECT NVL(MAX(no_revision), 0) + 1 FROM revisiones WHERE documento = :id_documento),
											:id_documento,
											:id_responsable,
											SYSDATE,
											1,
											null,
											:ruta
										);
												COMMIT;
							EXCEPTION
								WHEN OTHERS THEN
								  ROLLBACK;
							END;
	";
	
	$ejecuta_consulta_inserta = oci_parse($conexion, $consulta_inserta);
	oci_bind_by_name($ejecuta_consulta_inserta, ':id_documento', $revision->id_documento);
	oci_bind_by_name($ejecuta_consulta_inserta, ':id_responsable', $revision->id_responsable);
	oci_bind_by_name($ejecuta_consulta_inserta, ':ruta', $revision->ruta);
	$inserta = oci_execute($ejecuta_consulta_inserta);
	
	if($inserta){
		if(isset($revision_request['ubicacion'])){
			$ubicacion = trim($revision_request['ubicacion']);
			$consulta_revision = "UPDATE DOCUMENTOS SET ubicacion = :ubicacion WHERE id_documento = :id_documento";
			$ejecuta_consulta_revision = oci_parse($conexion, $consulta_revision);
			oci_bind_by_name($ejecuta_consulta_revision, ':ubicacion', $ubicacion);
			oci_bind_by_name($ejecuta_consulta_revision, ':id_documento', $revision->id_documento);
			
			$inserta_revision = oci_execute($ejecuta_consulta_revision);
			
			if($inserta_revision){
				$correcto = true;
				$respuesta = array('Exito' => 'Revisión creada correctamente');
				echo json_encode($respuesta);
			}
			else{
				$e = oci_error($ejecuta_consulta_revision);
				$respuesta = array('Error' => $e['message']);
				//$respuesta = array('Error' => 'Error al crear revisión del revision');
				echo json_encode($respuesta);
			}
			oci_free_statement($ejecuta_consulta_revision);
		}
		else{
			$correcto = true;
			$respuesta = array('Exito' => 'Revisión creada correctamente');
				echo json_encode($respuesta);
		}
		
	}
	else{
		$e = oci_error($ejecuta_consulta_inserta);
		$mensaje = explode('_', $e['message']);
		$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		//$respuesta = array('Error' => 'Error al crear revision');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_inserta);
	
	if($correcto){
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
		
		/*$consulta_proceso = "select b.nombre, b.id_proceso, a.nombre as documento, a.codigo from documentos a JOIN procesos b ON a.proceso = b.id_proceso WHERE a.id_documento = :id_documento";
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
		oci_free_statement($ejecuta_consulta_proceso);*/
		
		$consulta_no_revision = "select no_revision -1 as no_revision, id_revision from v_revisiones_vigentes where id_documento = :id_documento";
		$ejecuta_consulta_no_revision = oci_parse($conexion, $consulta_no_revision);
		oci_bind_by_name($ejecuta_consulta_no_revision, ':id_documento', $revision->id_documento);
		oci_execute($ejecuta_consulta_no_revision);
		while($row = oci_fetch_array($ejecuta_consulta_no_revision, OCI_ASSOC+OCI_RETURN_NULLS)){
			$revision->no_revision = $row['NO_REVISION'];
			$revision->id_revision = $row['ID_REVISION'];
		}
		oci_free_statement($ejecuta_consulta_no_revision);
		
		$consulta_usuario = "SELECT a.nombre ||' ' || a.apellido as nombre, a.correo, c.id_proceso, c.nombre as proceso,
								d.nombre as documento, d.codigo
							from usuarios a
								LEFT JOIN departamentos b ON b.lider = a.id_usuario
								LEFT JOIN procesos c ON c.departamento = b.id_depto
								LEFT JOIN documentos d ON d.proceso = c.id_proceso
								LEFT JOIN revisiones e ON e.documento = d.id_documento
								WHERE e.id_revision = :id_revision";
		$ejecuta_consulta_usuario = oci_parse($conexion, $consulta_usuario);
		oci_bind_by_name($ejecuta_consulta_usuario, ':id_revision', $revision->id_revision);
		oci_execute($ejecuta_consulta_usuario);
		if($row = oci_fetch_array($ejecuta_consulta_usuario, OCI_ASSOC+OCI_RETURN_NULLS)){
			$nombre = $row['NOMBRE'];
			$correo = $row['CORREO'];
			$proceso = new Proceso();
			$documento = new Documento();
			$proceso->id_proceso = $row['ID_PROCESO'];
			$proceso->nombre = $row['PROCESO'];
			$documento->nombre = $row['DOCUMENTO'];
			$documento->codigo = $row['CODIGO'];
		}
		oci_free_statement($ejecuta_consulta_usuario);
		
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
			//De parte de quien es el correo 
			$mail->SetFrom($correo_emisor, $nombre_emisor); 
			//Asunto del correo 
			$mail->Subject = utf8_decode("Cambio de revisión del documento ".$documento->codigo." en el SGC"); 
			//Mensaje alternativo en caso que el destinatario no pueda abrir correos HTML 
			$mail->AltBody = 'Para ver el mensaje necesita un cliente de correo compatible con HTML.'; 
			foreach($usuarios as $usuario){
				//La direccion a donde mandamos el correo 
				$mail->AddAddress($usuario['CORREO']); 
				$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
					Estimado(a) <strong>".$usuario['NOMBRE']."</strong> buen día.<br><br>
					<p>Le notificamos que se actualizo el documento <strong>".$documento->codigo." ".$documento->nombre."</strong> 
					a la revisión <strong>R".$revision->no_revision."</strong> en el Sistema Interno de Control de Documentos (SICDOC), así mismo, 
					solicitamos su valioso apoyo a fin de actualizar su lista de control de registros de su proceso.</p><br><br>
					<p><img src='cid:firma'></p>
					<p>Mensaje enviado de forma automática.</p>");
					//Enviamos el correo
					$mail->Send(); 
					$mail->ClearAllRecipients();
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