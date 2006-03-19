<?php 
	header("Content-type:text/xml");
	require_once('config.php'); 
	print("<?xml version=\"1.0\"?>");
	$id = $_GET["id"];
	$pid = $_GET["parent_id"];

	$link = mysql_pconnect($mysql_host, $mysql_user, $mysql_pasw);
	mysql_select_db ($mysql_db);
	
	saveNewParent($id,$pid);
	
	mysql_close($link);
	
	//creates xml show item details
	function saveNewParent($id,$pid){
		global $id_out;
		$sql = "Update Tree set item_parent_id=$pid where item_id=$id";
		$res = mysql_query($sql);
		if($res){
			$id_out = $id;
		}else{
			$id_out = "-1";
		}
	}
?>
<succeedded id="<?=$id_out?>"/>