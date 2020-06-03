<?php
/**
 * EGroupware - convert old config.tpl to et2 config.xet
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@stylite.de>
 * @copyright 2016 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

if (php_sapi_name() !== 'cli' && !empty($_GET['app']) && preg_match('/^[a-z0-9_-]+$/i', $_GET['app']))
{
	$app = $_GET['app'];
}
elseif ($_SERVER['argc'] > 1)
{
	$app = $_SERVER['argv'][1];
}
else
{
	$app = 'admin';
}

include __DIR__.'/../phpgwapi/inc/common_functions.inc.php';

$path = EGW_SERVER_ROOT.'/'.$app.'/templates/default/config.tpl';
if (!file_exists($path) || !($content = file_get_contents($path)))
{
	die("File not found: $path");
}
if (!preg_match('|<!-- BEGIN body -->(.*)<!-- END body -->|sui', $content, $table) &&
	!preg_match('|\<table[^>]*\>(.*)</table\>|sui', $content, $table))
{
	die('No BEGIN/END body or table tag found!');
}
$table[1] = preg_replace('/^<!-- (BEGIN|END)\s*[^ -]+-->/U', '', $table[1]);

if (!preg_match_all('|(<!--[^<-]*)?\s*<tr[^>]*>(.*)</tr>|Usui', $table[1], $trs, PREG_PATTERN_ORDER))
{
	die('No tr tags found!');
}
if (php_sapi_name() !== 'cli')
{
	EGroupware\Api\Header\Content::type('config.xet', 'text/plain', 0, true, false);
}
echo '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE overlay PUBLIC "-//EGroupware GmbH//eTemplate 2//EN" "http://www.egroupware.org/etemplate2.dtd">
<!-- $Id$ -->
<overlay>
	<template id="'.$app.'.config" template="" lang="" group="0" version="16.1">
		<grid width="100%" class="admin-config egwGridView_grid">
			<columns>
				<column width="60%"/>
				<column/>
			</columns>
			<rows>
';
foreach($trs[2] as $n => $tr)
{
	if (strpos($tr, '{title}') || strpos($tr, '<input type="submit"')) continue;

	if (!preg_match_all('|<td\s*([^>]*)>(.*)</td>|Usui', $tr, $tds, PREG_PATTERN_ORDER))
	{
		die("No td tags found in $n. tr: $tr");
	}
	if (($commented = !empty($trs[1][$n])? $trs[1][$n] : ''))
	{
		echo "\t\t\t\t$commented\n";
	}
	echo "\t\t\t\t<row>\n";
	foreach($tds[2] as $t => $td)
	{
		if (preg_match('|^\s*<([^ >]+)\s*([^/>]+)/?>(.*)\s*$|sui', $td, $matches))
		{
			$attrs = preg_match_all('|\s*([^=]+)="([^"]+)"|', $matches[2], $attrs) ?
				array_combine($attrs[1], $attrs[2]) : array();

			switch($matches[1])
			{
				case 'input':
				case 'textarea':
					echo "\t\t\t\t\t<textbox id=\"".$attrs['name'].'"';
					unset($attrs['value'], $attrs['name']);
					foreach($attrs as $name => $value)
					{
						if ($name == 'type' && $value == 'password') $value = 'passwd';
						echo " $name=\"$value\"";
					}
					echo "/>\n";
					if (trim($matches[3]) && $matches['1'] == 'input')
					{
						if ($commented)
						{
							echo $tr;
							continue 2;
						}
						echo "\t\t\t\t\t<!-- ".trim($matches[3])." -->\n";
					}

					break;

				case 'select':
					echo "\t\t\t\t\t<select id=\"".$attrs['name'].'"';
					unset($attrs['name']);
					foreach($attrs as $name => $value)
					{
						echo " $name=\"$value\"";
					}
					echo ">\n";
					if (preg_match_all('|<option\s+value="([^"]*)"\s*({selected_[^}]+})?>(.*)</option>|Usui', $matches[3], $options))
					{
						foreach($options[3] as $i => $label)
						{
							$label = preg_replace_callback('/{lang_([^}]+)}/', function($matches)
							{
								return '{'.str_replace('_', ' ', $matches[1]).'}';
							}, $label);
							// no need for spezial sub-string translation syntax
							if ($label[0] == '{' && strpos($label, '{', 1) === false && substr($label, -1) == '}')
							{
								$label = substr($label, 1, -1);
							}
							echo "\t\t\t\t\t\t<option value=\"".$options[1][$i].'">'.$label."</option>\n";
						}
					}
					else
					{
						echo "\t\t\t\t\t<!-- ".trim($matches[3])." -->\n";
					}
					echo "\t\t\t\t\t</select>\n";
					break;

				default:
					echo "\t\t\t\t\t<!-- $tr -->\n";
					break;
			}
		}
		elseif (preg_match('/^\s*([^{]*){lang_([^}]+)}\s*(.*)$/sui',
			str_replace(array('&nbsp;', '<b>', '</b>'), '', $td), $matches))
		{
			if (!$commented && trim($matches[1])) echo "\t\t\t\t\t<!-- $matches[1] -->\n";
			echo "\t\t\t\t\t<description value=\"".htmlspecialchars(str_replace('_', ' ', $matches[2])).'"';
			if (trim($matches[3]) == ':')
			{
				echo ' label="%s:"';
				unset($matches[3]);
			}
			if (strpos($tds[1][$t], 'colspan='))
			{
				echo ' span="all" class="subHeader"';
			}
			echo "/>\n";
			if (!$commented && !empty($matches[3]) && trim($matches[3])) echo "\t\t\t\t\t<!-- ".trim($matches[3])." -->\n";
		}
		elseif(!$commented)
		{
			echo "\t\t\t\t\t<!-- ".trim($td)." -->\n";
		}
	}
	echo "\t\t\t\t</row>".($commented ? ' -->' : '')."\n";
}
echo
'			</rows>
		</grid>
	</template>
</overlay>
';
