<?php
header("Content-Type: text/xml");

//include db connection settings
require_once("./config.php");

$link = mysql_pconnect($mysql_host, $mysql_user, $mysql_pasw);
$db = mysql_select_db ($mysql_db);

function get_menu_xml($parent_id, $top_id) {
	
	if (!isset($parent_id)) {
		// top level
		$xml = '<?xml version="1.0"?><menu>';
		$parent_id = "topId";
	} else {
		// sublevel
		$xml = '<?xml version="1.0"?><menu parentId="'.$parent_id.'">';
	}
	
	$res = mysql_query("SELECT `a`.`itemId`, `a`.`itemText`, `a`.`itemType`, `a`.`itemEnabled`, `a`.`itemChecked`, `a`.`itemGroup`, `a`.`itemImage`, `a`.`itemImageDis`, COUNT(`b`.`itemId`) AS `itemComplex` FROM `dhtmlxmenu` AS `a` LEFT JOIN (`dhtmlxmenu` AS `b`) ON (`b`.`itemParentId`=`a`.`itemId`) WHERE `a`.`itemParentId`='".mysql_real_escape_string($parent_id)."' GROUP BY `a`.`itemId` ORDER BY `a`.`itemOrder`");// or die(mysql_error());
	while ($out = mysql_fetch_object($res)) {
		$xml = $xml.'<item id="'.$out->itemId.'" text="'.$out->itemText.'"'.
			    (strlen($out->itemType)>0?' type="'.$out->itemType.'"':'').
			    ($out->itemEnabled=="0"?' enabled="false"':'').
			    ($out->itemChecked=="1"?' checked="true"':'').
			    (strlen($out->itemGroup)>0?' group="'.$out->itemGroup.'"':'').
			    (strlen($out->itemImage)>0?' img="'.$out->itemImage.'"':'').
			    (strlen($out->itemImageDis)>0?' imgdis="'.$out->itemImageDis.'"':'').
			    ($out->itemComplex>0?' complex="true"':'').
			    '/>';
	}
	mysql_free_result($res);
	//
	$xml = $xml.'</menu>';
	return $xml;
}

switch(@$_GET["action"]) {
	case "loadMenu":
		echo get_menu_xml(@$_GET["parentId"], @$_GET["topId"]);
		break;
}
mysql_close($link);
?>