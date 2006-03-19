<?php 
	require_once('config.php'); 

	$link = mysql_pconnect($mysql_host, $mysql_user, $mysql_pasw);
	mysql_select_db ($mysql_db);
	if($_POST["item_id"]==-1){
		saveInsert($_POST["item_nm"],$_POST["item_parent_id"],$_POST["item_desc"],0);
	}else{
		saveUpdate($_POST["item_id"],$_POST["item_nm"],$_POST["item_parent_id"],$_POST["item_desc"],0);
	}
	
	mysql_close($link);
	
	//insert item
	function saveInsert($name,$parent_id,$desc,$order){
		$sql = 	"Insert into tree(item_nm,item_parent_id,item_desc,item_order) ";
		$sql.= 	"Values('".addslashes($name)."',$parent_id,'".addslashes($desc)."',$order)";
		print($sql);
		$res = mysql_query($sql);
		$newId = mysql_insert_id();
		print("<script>top.doUpdateItem('$newId','$name');</script>");
		
	}
	//insert item
	function saveUpdate($id,$name,$parent_id,$desc,$order){
		$sql = 	"Update tree set item_nm = '".addslashes($name)."',item_parent_id = $parent_id,item_desc = '".addslashes($desc)."',item_order = $order where item_id=$id";
		print($sql);
		$res = mysql_query($sql);
		print("<script>top.doUpdateItem('$id','".addslashes($name)."');</script>");
		
	}
?>
