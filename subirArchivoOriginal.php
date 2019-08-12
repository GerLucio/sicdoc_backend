<?php
	header("Access-Control-Allow-Origin: *");
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	 
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		echo json_encode(array('Error' => 'Método inválido'));
		exit;
	}

	$path = '../../../files_sicdoc/';
	$formatos = array(".xlsx", ".xls", ".doc", ".docx", ".pdf");
	$formato_valido = false;

	if (isset($_FILES)) {
		$nombre_original = $_FILES['archivo']['name'];
		$extension = '.'.pathinfo($nombre_original, PATHINFO_EXTENSION);
		foreach($formatos as $formato){
			if($extension == $formato){
				$formato_valido = true;
				break;
			}
		}
		if(!$formato_valido){
			unlink($_FILES['archivo']['tmp_name']);
			echo json_encode(
				array('Error' => 'Formato de archivo inválido')
			);
			exit;
		}
		$nombre_generado = str_replace(' ', '_', substr($nombre_original, 0, 512));
		//$nombre_generado = substr($nombre_original, 0, 512);
		$filePath = $path.$nombre_generado;
	 
		if (!is_writable($path)) {
			echo json_encode(array(
				'Error' => 'No se puede escribir en el directorio'
			));
			exit;
		}
	 
		if (move_uploaded_file($_FILES['archivo']['tmp_name'], $filePath)) {
			echo json_encode(array(
				'Exito'        => true,
				'nombre_original'  => $nombre_original,
				'nombre_generado' => $nombre_generado
			));
		}
	}
	else {
		echo json_encode(
			array('Error' => 'No se encontró archivo')
		);
		exit;
	}


?>
