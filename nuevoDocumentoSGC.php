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
	include 'clases/clase_proceso.php';
	//include ("PHPMailer/class.phpmailer.php"); 
	//include ("PHPMailer/class.smtp.php");
	include 'clases/Clase_Cifra.php';
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
	
	$documento = new Documento();
	$documento->nombre = trim($documento_request['documento']['nombre']);
	$documento->tipo = trim($documento_request['documento']['id_tipo']);
	$documento->proceso = trim($documento_request['documento']['id_proceso']);
	$documento->codigo = trim($documento_request['documento']['codigo']);
	$documento->ubicacion = trim($documento_request['documento']['ubicacion']);
	
	$ruta = trim($documento_request['nombre_archivo']);
	$responsable = trim($documento_request['responsable']);
	$correcto = false;
	
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
										:codigo,
										SYSDATE,
										null,
										:ubicacion,
										null,
										1
									)";
	
	$ejecuta_consulta_inserta = oci_parse($conexion, $consulta_inserta);
	oci_bind_by_name($ejecuta_consulta_inserta, ':nombre', $documento->nombre);
	oci_bind_by_name($ejecuta_consulta_inserta, ':tipo', $documento->tipo);
	oci_bind_by_name($ejecuta_consulta_inserta, ':proceso', $documento->proceso);
	oci_bind_by_name($ejecuta_consulta_inserta, ':codigo', $documento->codigo);
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
			$correcto = true;
			$respuesta = array('Exito' => 'Documento creado correctamente');
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
		
			//A que dirección se puede responder el correo 
			$mail->AddReplyTo($correo_emisor, $nombre_emisor);
			//De parte de quien es el correo 
			$mail->SetFrom($correo_emisor, $nombre_emisor); 
			//Mensaje alternativo en caso que el destinatario no pueda abrir correos HTML 
			$mail->AltBody = 'Para ver el mensaje necesita un cliente de correo compatible con HTML.'; 
			$mail->AddEmbeddedImage('img/firma_sicdoc.jpg', 'firma');
			
			$consulta_proceso = "select nombre from procesos where id_proceso = :proceso and
			UPPER(nombre) in(UPPER('Auditoria Interna'), UPPER('Medición, análisis y mejora'), 
			UPPER('Revisión por la Dirección'), UPPER('Coordinación del Sistema de Gestión de la Calidad'))";
			$ejecuta_consulta_proceso = oci_parse($conexion, $consulta_proceso);
			oci_bind_by_name($ejecuta_consulta_proceso, ':proceso', $documento->proceso);
			oci_execute($ejecuta_consulta_proceso);
			$row = oci_fetch_array($ejecuta_consulta_proceso, OCI_ASSOC+OCI_RETURN_NULLS);
			if($row == false){
				$consulta_usuario = "SELECT b.nombre ||' ' || b.apellido as nombre, b.correo from usuarios b 
										JOIN departamentos a on a.lider = b.id_usuario
										JOIN v_documentos c on c.id_depto = a.id_depto
										WHERE c.codigo = :codigo";
				$ejecuta_consulta_usuario = oci_parse($conexion, $consulta_usuario);
				oci_bind_by_name($ejecuta_consulta_usuario, ':codigo', $documento->codigo);
				oci_execute($ejecuta_consulta_usuario);
				if($row = oci_fetch_array($ejecuta_consulta_usuario, OCI_ASSOC+OCI_RETURN_NULLS)){
					$nombre = $row['NOMBRE'];
					$correo = $row['CORREO'];
				}
				oci_free_statement($ejecuta_consulta_usuario);
			
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
				//La direccion a donde mandamos el correo 
				$correo_destino=$correo;
				$nombre_destino=utf8_decode($nombre);                //Nombre de quien recibe
				$mail->AddAddress($correo_destino, $nombre_destino);
				//Asunto del correo 
				$mail->Subject = utf8_decode("Registro del documento ".$documento->codigo." en el SGC"); 
				//El cuerpo del mensaje, puede ser con etiquetas HTML 
				$mail->isHTML(true);
				$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
				  Estimado(a) <strong>".$nombre."</strong> buen día.<br><br>
					<p>Le notificamos que se dio de alta un nuevo documento del SGC 
					<strong>".$documento->codigo." ".$documento->nombre."</strong> en el Sistema Interno de Control de Documentos (SICDOC), 
					así mismo, solicitamos su valioso apoyo a fin de actualizar su lista de control de registros <strong>SGC-05</strong>.</p><br><br>
					<p><img src='cid:firma'></p>
					<p>Mensaje enviado de forma automática.</p>");
				foreach($usuarios as $usuario){
					$mail->AddCC($usuario['CORREO'], utf8_decode($usuario['NOMBRE']));
				}
				//Enviamos el correo
				$mail->Send(); 
			}
			else{
				$consulta_usuarios = "SELECT nombre ||' '|| apellido as nombre,
						correo 
						FROM v_usuarios_activos";
				$ejecuta_consulta_usuarios = oci_parse($conexion, $consulta_usuarios);
				oci_execute($ejecuta_consulta_usuarios);
				while($row = oci_fetch_array($ejecuta_consulta_usuarios, OCI_ASSOC+OCI_RETURN_NULLS)){
					$usuarios[] = $row;
				}
				oci_free_statement($ejecuta_consulta_usuarios);
				$mail->Subject = utf8_decode("Registro del documento ".$documento->codigo." en el SGC");
				//El cuerpo del mensaje, puede ser con etiquetas HTML 
				$mail->isHTML(true);
				foreach($usuarios as $usuario){
					$mail->AddAddress($usuario['CORREO']);
					$mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
					Estimado(a) <strong>".$usuario['NOMBRE']."</strong> buen día.<br><br>
					<p>Le notificamos que se dio de alta un nuevo documento: <strong>".$documento->codigo." ".$documento->nombre."</strong> 
					en el Sistema Interno de Control de Documentos (SICDOC), así mismo, 
					solicitamos su valioso apoyo a fin de actualizar su lista de control de registros <strong>SGC-05</strong>.</p><br><br>
					<p><img src='cid:firma'></p>
					<p>Mensaje enviado de forma automática.</p>");
					//Enviamos el correo
					$mail->Send(); 
					$mail->ClearAllRecipients();
				}
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