<?PHP
	error_reporting(0);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	//header('content-type: application/json;');
	
	include 'clases/clase_documento.php';
	include 'conexion/conexion.php';
	include 'clases/clase_token.php';
	include 'clases/clase_proceso.php';
	include 'clases/clase_usuario.php';
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

	$path = '../../../files_sicdoc/';
	$cambio = trim($documento_request['cambio']);
	$nombre_archivo = trim($documento_request['nombre_archivo']);
	$ruta = trim($documento_request['documento']['ruta']);

	$documento = new Documento();
	$documento->id_documento = trim($documento_request['documento']['id_documento']);
	$documento->nombre = trim($documento_request['documento']['nombre']);
	$documento->codigo = trim($documento_request['documento']['codigo']);
	$documento->tipo = trim($documento_request['documento']['id_tipo']);
	$documento->proceso = trim($documento_request['documento']['id_proceso']);
	$documento->ubicacion = trim($documento_request['documento']['ubicacion']);
	
	
	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_inserta = "update documentos set
								nombre = :nombre,
								codigo = :codigo,
								tipo = :tipo,
								proceso = :proceso,
								ubicacion = :ubicacion
								where id_documento = :id_documento
	";
	
	$ejecuta_consulta_inserta = oci_parse($conexion, $consulta_inserta);
	oci_bind_by_name($ejecuta_consulta_inserta, ':nombre', $documento->nombre);
	oci_bind_by_name($ejecuta_consulta_inserta, ':codigo', $documento->codigo);
	oci_bind_by_name($ejecuta_consulta_inserta, ':tipo', $documento->tipo);
	oci_bind_by_name($ejecuta_consulta_inserta, ':proceso', $documento->proceso);
	oci_bind_by_name($ejecuta_consulta_inserta, ':ubicacion', $documento->ubicacion);
	oci_bind_by_name($ejecuta_consulta_inserta, ':id_documento', $documento->id_documento);
	$inserta = oci_execute($ejecuta_consulta_inserta);

	if($inserta){

		$consulta_revision = "update revisiones set ruta = :ruta where documento = :documento";
		$ejecuta_consulta_revision = oci_parse($conexion, $consulta_revision);
		oci_bind_by_name($ejecuta_consulta_revision, ':ruta', $nombre_archivo);
		oci_bind_by_name($ejecuta_consulta_revision, ':documento', $documento->id_documento);
		
		$inserta_revision = oci_execute($ejecuta_consulta_revision);
		
		if($inserta_revision){
			if($cambio && $ruta != $nombre_archivo){
				unlink($path.$ruta);
			}
			else{
				rename($path.$ruta, $path.$nombre_archivo);
			}
			$respuesta = array('Exito' => 'Documento modificado correctamente');
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
			$respuesta = array('Error' => 'No se puede repetir el nombre ni código del documento');
		else{
			$mensaje = explode('_', $e['message']);
			$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		}
		//$respuesta = array('Error' => 'Error al crear documento');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_inserta);
	oci_close($conexion);
	
	exit;
	
?>