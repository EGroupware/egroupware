<?php
/**
 * EGroupware Setup - fixes a mysql DB to match our system_charset
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// if we are NOT called as part of an update script, behave like a regular setup script
if (!isset($GLOBALS['egw_setup']) || !is_object($GLOBALS['egw_setup']))
{
	$diagnostics = 1;	// can be set to 0=non, 1=some (default for now), 2=all

	include('./inc/functions.inc.php');
	// Authorize the user to use setup app and load the database
	// Does not return unless user is authorized
	if (!$GLOBALS['egw_setup']->auth('Config') || @$_POST['cancel'])
	{
		Header('Location: index.php');
		exit;
	}
	$GLOBALS['egw_setup']->loaddb();

	$tpl_root = $GLOBALS['egw_setup']->html->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
	));
	$GLOBALS['egw_setup']->html->show_header('',False,'config',$GLOBALS['egw_setup']->ConfigDomain . '(' . $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain]['db_type'] . ')');
	echo '<h3>'.'Fix mysql DB to match the EGroupware system_charset'."</h3>\n";
	$running_standalone = true;
}
$db =& $GLOBALS['egw_setup']->db;
$charset2mysql =& $GLOBALS['egw_setup']->db->Link_ID->charset2mysql;
$mysql2charset = array_flip($charset2mysql);

$ServerInfo = $db->Link_ID->ServerInfo();
$db_version = (float) $ServerInfo['version'];

if ($running_standalone || $_REQUEST['debug']) echo "<p>DB-Type='<b>{$GLOBALS['egw_setup']->db->Type}</b>', DB-Version=<b>$db_version</b> ($ServerInfo[description]), EGroupware system_charset='<b>{$GLOBALS['egw_setup']->system_charset}</b>', DB-connection charset was '<b>{$GLOBALS['egw_setup']->db_charset_was}</b>'</p>\n";

$mysql_system_charset = isset($charset2mysql[$GLOBALS['egw_setup']->system_charset]) ?
	$charset2mysql[$GLOBALS['egw_setup']->system_charset] : $GLOBALS['egw_setup']->system_charset;

if (substr($db->Type,0,5) == 'mysql' && $db_version >= 4.1 && $GLOBALS['egw_setup']->system_charset && $GLOBALS['egw_setup']->db_charset_was &&
	$GLOBALS['egw_setup']->system_charset != $GLOBALS['egw_setup']->db_charset_was)
{
	$tables_modified = 'no';

	$tables = array();
	$db->query("SHOW TABLE STATUS",__LINE__,__FILE__);
	while (($row = $db->row(true)))
	{
		$tables[$row['Name']] = $row['Collation'];
	}
	foreach($tables as $table => $collation)
	{
		$columns = array();
		$db->query("SHOW FULL FIELDS FROM `$table`",__LINE__,__FILE__);
		while(($row = $db->row(true)))
		{
			$columns[] = $row;
		}
		//echo $table; _debug_array($columns);
		$fulltext = $fulltext_back = array();
		$db->query("SHOW KEYS FROM `$table`",__LINE__,__FILE__);
		while(($row = $db->row(true)))
		{
			if ($row['Index_type'] == 'FULLTEXT')
			{
				$fulltext[$row['Column_name']] = $row['Key_name'];
			}
		}

		$alter_table = $alter_table_back = array();
		foreach($columns as $column)
		{
			if ($column['Collation'] && preg_match('/^(char|varchar|.*text)\(?([0-9]*)\)?$/i',$column['Type'],$matches))
			{
				list(,$type,$size) = $matches;
				list($charset) = explode('_',$column['Collation']);

				if (isset($mysql2charset[$charset])) $charset = $mysql2charset[$charset];

				if ($charset != $GLOBALS['egw_setup']->system_charset)
				{
					$col = $column['Field'];

					if ($type == 'varchar' || $type == 'char')	// old schema_proc (pre 1.0.1) used also char
					{
						$type = 'varchar('.$size.')';
						$bintype = 'varbinary('.$size.')';
					}
					else
					{
						$bintype = str_replace('text','blob',$type);
					}
					//echo "<p>$table.$col $type CHARACTER SET $charset $default $null</p>\n";

					$default = !is_null($column['Default']) ? "DEFAULT '".$column['Default']."'" : '';
					$null = $column['Null'] ? 'NULL' : 'NOT NULL';

					if (isset($fulltext[$col]))
					{
						$idx_name = $fulltext[$col];
						$idx_cols = array();
						foreach($fulltext as $c => $i)
						{
							if ($i == $idx_name)
							{
								$idx_cols[] = $c;
								unset($fulltext[$c]);
							}
						}
						$fulltext_back[$idx_name] = $idx_cols;
						$alter_table[] = " DROP INDEX `$idx_name`";
					}
					$alter_table[] = " CHANGE `$col` `$col` $bintype $default $null";
					$alter_table_back[] = " CHANGE `$col` `$col` $type CHARACTER SET $mysql_system_charset $default $null";
				}
			}
		}
		list($charset) = explode('_',$collation);
		if (isset($mysql2charset[$charset])) $charset = $mysql2charset[$charset];
		if ($charset != $GLOBALS['egw_setup']->system_charset)
		{
			$alter_table[] = " DEFAULT CHARACTER SET $mysql_system_charset";
		}
		if (count($alter_table))
		{
			$alter_table = "ALTER TABLE $table\n".implode(",\n",$alter_table);

			if ($running_standalone || $_REQUEST['debug']) echo '<p>'.nl2br($alter_table)."</p>\n";
			if (!$db->query($alter_table,__LINE__,__FILE__))
			{
				echo "<p>SQL Error: ".nl2br($alter_table)."</p>\n";
				echo "<b>{$db->Type} Error</b>: {$db->Errno} ({$db->Error})</p>\n";
				echo "<p>continuing ...</p>\n";
				continue;
			}
			foreach($fulltext_back as $idx_name => $idx_cols)
			{
				$alter_table_back[] = " ADD FULLTEXT `$idx_name` (`".implode('`,`',$idx_cols)."`)";
			}
			if (count($alter_table_back))
			{
				$alter_table_back = "ALTER TABLE $table\n".implode(",\n",$alter_table_back);

				if ($running_standalone || $_REQUEST['debug']) echo '<p>'.nl2br($alter_table_back)."</p>\n";
				if (!$db->query($alter_table_back,__LINE__,__FILE__))
				{
					echo "<p><b>SQL Error</b>: ".nl2br($alter_table_back)."</p>\n";
					echo "<b>{$db->Type} Error</b>: {$db->Errno} ({$db->Error})</p>\n";
					echo "<p>continuing ...</p>\n";
					continue;
				}
			}
			++$tables_modified;
		}
	}
	// change the default charset of the DB
	$db->query("SHOW CREATE DATABASE `$db->Database`",__LINE__,__FILE__);
	$create_db = $db->next_record() ? $db->f(1) : '';
	if (preg_match('/CHARACTER SET ([a-z1-9_-]+) /i',$create_db,$matches) && $matches[1] != $mysql_system_charset)
	{
		$alter_db = "ALTER DATABASE `$db->Database` DEFAULT CHARACTER SET $mysql_system_charset";
		if ($running_standalone || $_REQUEST['debug']) echo '<p>'.$alter_db."</p>\n";
		$db->query($alter_db,__LINE__,__FILE__);
	}
}
if ($running_standalone || $_REQUEST['debug'])
{
	echo "<p>$tables_modified tables changed to our system_charset {$GLOBALS['egw_setup']->system_charset}($mysql_system_charset)</p>\n";

	if ($running_standalone) $GLOBALS['egw_setup']->html->show_footer();
}
