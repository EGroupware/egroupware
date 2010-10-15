<?php 
	error_reporting(E_ALL ^ E_NOTICE);
	
header("Content-type:text/xml"); print("<?xml version=\"1.0\"?>");
if (isset($_GET["id"]))
	$url_var=$_GET["id"];
else
	$url_var=0;
print("<tree id='".$url_var."'>");
         for ($inta=0; $inta<4; $inta++)
			 print("<item child='1' id='".$url_var."_".$inta."' text='Item ".$url_var."-".$inta."'><userdata name='ud_block'>ud_data</userdata></item>");
print("</tree>");
?> 
