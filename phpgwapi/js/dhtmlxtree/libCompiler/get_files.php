<?php
	error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
	require_once("images.php");
	$location = process_request($_POST['files'],$_POST['chunks'],$_POST['skin'],0);
?>
<html>
<head>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<title></title>
	<style>
		body, html{
			height:100%;
			font-family:Tahoma;
			font-size:12px;
		}
	</style>
	<body>
		Ready code stored at <?php echo $location;?><br/><br/><br/>
		<a href='zip.php?location=<?php echo urlencode($location);?>' target="_blank">Download generated files</a><br/><br/>
	</body>
</head>
</html>