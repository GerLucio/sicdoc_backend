<?PHP
	error_reporting(0);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_usuario.php';
	include 'clases/clase_token.php';
	include 'conexion/conexion.php';
	
	$usuario_request = json_decode(file_get_contents("php://input"), true);
	$tkn = $usuario_request['tkn'];
	$token = new Token();
	$tkn_valido = $token->validaToken($tkn);
	
	if(!$tkn_valido){
		$respuesta = array('ErrorToken' => 'Token no válido');
		echo json_encode($respuesta);
		exit;
	}
	
	$contador = 0;

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_usuarios = "SELECT id_usuario, nombre, apellido, puesto, correo, id_departamento, departamento, subdireccion, id_rol, 
			rol, estado, id_estado
				FROM v_usuarios_activos ORDER BY nombre, apellido";
	
	$ejecuta_consulta_usuarios = oci_parse($conexion, $consulta_usuarios);
	oci_execute($ejecuta_consulta_usuarios);
	while($row = oci_fetch_array($ejecuta_consulta_usuarios, OCI_ASSOC+OCI_RETURN_NULLS)){
		if($row['ID_ESTADO'] == 2)
			$row['ESTADO'] = "Cambio contraseña";
		$respuesta[$contador++] = $row;	
	}
	oci_free_statement($ejecuta_consulta_usuarios);
	oci_close($conexion);
	
	echo json_encode($respuesta);
	exit;
	
?>