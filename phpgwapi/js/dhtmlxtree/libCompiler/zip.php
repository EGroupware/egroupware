<?php
	error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
	
	header("Content-type:application/zip");
	header("Content-Disposition: attachment; filename=dhtmlx.zip");
	
	include('./convert.php');
	include("./ziplib.php");
	
	echo zipFromLocation($_GET['location']);
?>