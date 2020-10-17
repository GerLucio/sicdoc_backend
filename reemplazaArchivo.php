<?PHP
	error_reporting(0);
    //error_reporting(E_ALL);
    //ini_set('display_errors', TRUE);
    //ini_set('display_startup_errors', TRUE);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_token.php';

	
	$usuario_request = json_decode(file_get_contents("php://input"), true);
	$tkn = $usuario_request['tkn'];
	$token = new Token();
	$tkn_valido = $token->validaToken($tkn);
	
	if(!$tkn_valido){
		$respuesta = array('ErrorToken' => 'Token no válido');
		echo json_encode($respuesta);
		exit;
	}
	
	$nuevo = $usuario_request['nuevo'];
	$anterior = $usuario_request['anterior'];
	$path = '../../../files_sicdoc/';
	$correcto = rename($path.$anterior, $path.$nuevo);
	if($correcto){
		$respuesta = array('Exito' => 'Archivo subido correctamente');
		echo json_encode($respuesta);
	}
	else{
		$respuesta = array('Error' => 'Error al subir el nuevo archivo');
		echo json_encode($respuesta);
	}
	exit;
	
?>