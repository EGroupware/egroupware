<HTML>
<HEAD>
</HEAD>
<BODY STYLE="background-color: #ffffff;">
Welcome to the phpGroupWare Schema Abstraction Definition testing program.<BR>
Here we go:<P>
<?php
  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("../header.inc.php");
  include("./inc/functions.inc.php");

  $SetupDomain = "phpgroupware.org";
$tables = array(
		"departments" => array(
			"id" => array(
				"type" => "autoincrement"
			),
			"short" => array(
				"type" => "varchar",
				"precision" => 20,
				"nullable" => false
			),
			"name" => array(
				"type" => "varchar",
				"precision" => 50,
				"nullable" => false
			),
			"active" => array(
				"type" => "char",
				"precision" => 1,
				"nullable" => false,
				"default" => "Y"
			)
		),
		"actions" => array(
			"id" => array(
				"type" => "autoincrement"
			),
			"short" => array(
				"type" => "varchar",
				"precision" => 20,
				"nullable" => false
			),
			"name" => array(
				"type" => "varchar",
				"precision" => 50,
				"nullable" => false
			),
			"active" => array(
				"type" => "char",
				"precision" => 1,
				"nullable" => false,
				"default" => "Y"
			)
		),
		"timecards" => array(
			"id" => array(
				"type" => "autoincrement"
			),
			"jcn" => array(
				"type" => "int",
				"precision" => 4,
				"nullable" => false
			),
			"seq" => array(
				"type" => "int",
				"precision" => 4,
				"nullable" => false
			),
			"actionon" => array(
				"type" => "timestamp",
				"nullable" => false,
				"default" => "current_timestamp"
			),
			"inputon" => array(
				"type" => "timestamp",
				"nullable" => false,
				"default" => "current_timestamp"
			),
			"actionby" => array(
				"type" => "int",
				"precision" => 4,
				"nullable" => false
			),
			"status" => array(
				"type" => "int",
				"precision" => 4,
				"nullable" => false
			),
			"action" => array(
				"type" => "int",
				"precision" => 4,
				"nullable" => false
			),
			"hours" => array(
				"type" => "float",
				"precision" => 4,
				"nullable" => false,
				"default" => 0.0
			),
			"summary" => array(
				"type" => "varchar",
				"precision" => 80,
				"nullable" => false
			),
			"description" => array(
				"type" => "varchar",
				"precision" => 1024,
				"nullable" => true
			),
			"revision" => array(
				"type" => "varchar",
				"precision" => 20,
				"nullable" => true
			)
		)
	);

$phpgw_schema_proc->GenerateScripts($tables, true);
?>
</BODY>
</HTML>
