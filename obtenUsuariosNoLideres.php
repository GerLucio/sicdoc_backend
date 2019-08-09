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
	
	$consulta_usuarios = "SELECT a.id_usuario,
		a.nombre,
        b.lider,
		a.apellido,
		a.puesto,
		a.correo,
		a.departamento as id_departamento,
		b.nombre as departamento,
		c.nombre as subdireccion,
		a.rol as id_rol,
		d.rol,
		a.estado as id_estado,
		e.estado
		FROM usuarios a
			LEFT JOIN departamentos b ON a.departamento = b.id_depto
			LEFT JOIN subdirecciones c ON b.subdireccion = c.id_subdireccion
			LEFT JOIN roles d ON  d.id_rol = a.rol
			LEFT JOIN estados e ON  e.id_estado = a.estado
		WHERE a.estado != 0
			AND b.estado !=0
			AND c.estado !=0
			AND d.estado !=0
            AND a.id_usuario != b.lider
			AND a.rol != 1";
	
	$ejecuta_consulta_usuarios = oci_parse($conexion, $consulta_usuarios);
	oci_execute($ejecuta_consulta_usuarios);
	while($row = oci_fetch_array($ejecuta_consulta_usuarios, OCI_ASSOC+OCI_RETURN_NULLS)){
		$respuesta[$contador++] = $row;
	}
	oci_free_statement($ejecuta_consulta_usuarios);
	oci_close($conexion);
	
	echo json_encode($respuesta);
	exit;
	
?>