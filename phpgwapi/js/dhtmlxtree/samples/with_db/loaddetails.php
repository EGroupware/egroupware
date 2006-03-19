<?php 
	header("Content-type:text/xml");
	require_once('config.php'); 
	print("<?xml version=\"1.0\"?>");
	$id = $_GET["id"];
?>
<details id="<?=$id?>">
<?php 
	$link = mysql_pconnect($mysql_host, $mysql_user, $mysql_pasw);
	mysql_select_db ($mysql_db);
	
	loadDetails($id);
	
	mysql_close($link);
	
	//creates xml show item details
	function loadDetails($id){
		$sql = "Select item_nm,item_parent_id,item_desc from Tree where item_id=$id";
		$res = mysql_query($sql);
		if($res){
			while($row=mysql_fetch_array($res)){
				print("<name>".$row['item_nm']."</name>");
				print("<parent_id>".$row['item_parent_id']."</parent_id>");
				print("<desc>".$row['item_desc']."</desc>");
			}
			
		}else{
			echo mysql_errno().": ".mysql_error()." at ".__LINE__." line in ".__FILE__." file<br>";
		}
	}
?>
</details>