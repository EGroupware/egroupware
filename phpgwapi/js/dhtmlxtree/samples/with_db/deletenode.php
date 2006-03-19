<?php 
	require_once('config.php'); 

	$link = mysql_pconnect($mysql_host, $mysql_user, $mysql_pasw);
	mysql_select_db ($mysql_db);
	deleteNode($_POST["item_id"]);
	mysql_close($link);
	
	//insert item
	function deleteNode($id){
		deleteBranch($id);
		deleteSingleNode($id);
		print("<script>top.doDeleteTreeItem('$id');</script>");
	}
	function deleteSingleNode($id){
		$d_sql = "Delete from tree where item_id=".$id;
		$resDel = mysql_query($d_sql);
	}
	function deleteBranch($pid){
		$s_sql = "Select item_id from tree where item_parent_id=$pid";
		$res = mysql_query($s_sql);
		if($res){
			while($row=mysql_fetch_array($res)){
				deleteBranch($row['item_id']);
				deleteSingleNode($row['item_id']);
			}
			
		}
	}
?>