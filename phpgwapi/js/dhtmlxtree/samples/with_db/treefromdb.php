<?php 
	header("Content-type:text/xml");
	require_once('config.php'); 
	print("<?xml version=\"1.0\"?>");
?>
<tree id="0">
<?php
	$link = mysql_pconnect($mysql_host, $mysql_user, $mysql_pasw);
	$db = mysql_select_db ($mysql_db);
	//Create database and table if doesn't exists
	if(!$db){
		//mysql_create_db($mysql_db,$link);
		$sql = "Create database ".$mysql_db;
		$res = mysql_query ($sql);
		$sql = "use ".$mysql_db;
		$res = mysql_query ($sql);
		$sql = "CREATE TABLE Tree (item_id INT UNSIGNED not null AUTO_INCREMENT,item_nm VARCHAR (200) DEFAULT '0',item_order INT  UNSIGNED DEFAULT '0',item_desc TEXT ,item_parent_id INT UNSIGNED DEFAULT '0',PRIMARY KEY ( item_id ))";
		$res = mysql_query ($sql);
		if(!$res){
			echo mysql_errno().": ".mysql_error()." at ".__LINE__." line in ".__FILE__." file<br>";
		}
	}
	getLevelFromDB(0);
	mysql_close($link);
	
	//print one level of the tree, based on parent_id
	function getLevelFromDB($parent_id){
		$sql = "Select item_id, item_nm from Tree where item_parent_id=$parent_id";
		$res = mysql_query ($sql);
		if($res){
			while($row=mysql_fetch_array($res)){
				print("<item id='".$row['item_id']."' text=\"". str_replace('"',"&quot;",$row['item_nm'])."\">");
				getLevelFromDB($row['item_id']);
				print("</item>");
			}
		}else{
			echo mysql_errno().": ".mysql_error()." at ".__LINE__." line in ".__FILE__." file<br>";
		}
	}
?>
</tree>