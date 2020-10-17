<?PHP
	error_reporting(0);
    //error_reporting(E_ALL);
    //ini_set('display_errors', TRUE);
    //ini_set('display_startup_errors', TRUE);
	
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
	
	$alta_correcta = false;
	
	$revision = new Revision();
	$revision->id_documento = trim($revision_request['revision']['id_documento']);
	$revision->id_responsable = trim($revision_request['revision']['id_responsable']);
	$revision->ruta = trim($revision_request['nombre_archivo']);

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_inserta = "insert into revisiones values(
											(SELECT NVL(MAX(id_revision), 0) + 1 FROM revisiones),
											(SELECT NVL(MAX(no_revision), 0) + 1 FROM revisiones WHERE documento = :id_documento),
											:id_documento,
											:id_responsable,
											SYSDATE,
											2,
											null,
											:ruta
										)";
	
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
				$alta_correcta = true;				
				$respuesta = array('Exito' => 'Revisión enviada para su aprobación');
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
			$alta_correcta = true;
			$respuesta = array('Exito' => 'Revisión enviada para su aprobación');
				echo json_encode($respuesta);
		}
		
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
	}
	else{
		$e = oci_error($ejecuta_consulta_inserta);
		$mensaje = explode('_', $e['message']);
		$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		//$respuesta = array('Error' => 'Error al crear revision');
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
			//$mail->AddEmbeddedImage('img/firma_sicdoc.jpg', 'firma');
		
			$correo_destino=$coordinador->correo;      //Correo de quien recibe 
			$nombre_destino=utf8_decode($coordinador->nombre);                //Nombre de quien recibe 
			//A que dirección se puede responder el correo 
			$mail->AddReplyTo($correo_emisor, $nombre_emisor); 
			//La direccion a donde mandamos el correo 
			$mail->AddAddress($correo_destino, $nombre_destino); 
			//De parte de quien es el correo 
			$mail->SetFrom($correo_emisor, $nombre_emisor); 
			//Asunto del correo 
			  
			$mail->Subject = utf8_decode("Solicitud de cambio de revisión de un documento"); 
			$mail->isHTML(true);
			  $mail->Body = utf8_decode("<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/> 
				<p>
				El proceso <strong>".$proceso->nombre."</strong> solicita el cambio de revisión del documento 
				<strong>".$documento->codigo." ".$documento->nombre."</strong>.</p><br><br>
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