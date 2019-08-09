<?php
	/**incluye el archivo de la clase cifra donde vienen las varibles de conexion cifradas*/
	include_once 'Clase_Cifra.php';
	/**creo el objeto de la clase cifra*/
	$datos = new Cifra();
	
	$codificacion = 'AL32UTF8';
	
	/**variables que contienen el usuario, contraseña, servidor, puerto e instancia para la conexion*/
	$user = $datos->descifra($datos->user);
	$pass = $datos->descifra($datos->pass);
	$db = $datos->descifra($datos->db);
	
	/**Se realiza la conexion y el resultado se asigna a la variable conexion*/
	$conexion = oci_connect($user, $pass, $db, $codificacion);
?>