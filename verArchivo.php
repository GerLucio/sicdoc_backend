<?PHP
	header("Access-Control-Allow-Origin: *");
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('Content-type: text/xml');
	
	$archivo = $_GET['file'];
	$ruta = '../../files_sicdoc/'.$archivo;
	header ("Content-Disposition: attachment; filename=".$archivo."");
	header ("Content-Type: application/octet-stream");
	header ("Content-Length: ".filesize($ruta));
	readfile($ruta);
?>