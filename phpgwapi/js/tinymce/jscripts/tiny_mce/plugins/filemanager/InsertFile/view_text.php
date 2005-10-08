<?php	
	/**************************************************************************\
	* eGroupWare - Insert File Dialog, File Manager -plugin for tinymce        *
	* http://www.eGroupWare.org                                                *
	* Authors Al Rashid <alrashid@klokan.sk>                                   *
	*     and Xiang Wei ZHUO <wei@zhuo.org>                                    *
	* Modified for eGW by Cornelius Weiss <egw@von-und-zu-weiss.de>            *
	* --------------------------------------------                             *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; version 2 of the License.                      *
	\**************************************************************************/
	
class view_text {

	function folder_item($params)
	{
		return '<tr id="D'.$params['folderNb'].'">
		<td width="4%"><img src="img/ext/folder_small.gif" width="16" height="16" border="0" alt="'.$params['entry'].'" /></td>
		<td width="50%"><div style="height:15px; overflow:hidden;"><a href="javascript:changeDir('.$params['folderNb'].');" title="'.$params['entry'].'">'.$params['entry'].'</a></div></td>
		<td width="18%" align="right">'.$params['MY_MESSAGES']['folder'].'</td>
		<td width="25%">'.$params['parsed_time'].'</td>
		<td width="0px" style="display: none;">&nbsp;</td>
		<td width="0px" style="display: none;">&nbsp;</td>
		<td width="0px" style="display: none;">'.$params['time'].'</td>
		</tr>';
	}
	
	function files_item($params)
	{
		$trId = (int)$params['fileNb'] + 1;
		return '<tr id="F'.$params['fileNb'].'">
		<td width="4%"><img src="'.$params['parsed_icon'].'" width="16" height="16" border="0" alt="'.$params['entry'].'" /></td>
		<td width="50%"><div style="height:15px; overflow:hidden;"><a href="javascript:;" onClick="javascript:fileSelected(\''.$params['MY_BASE_URL'].$params['relativePath'].'\',\''.$params['entry'].'\',\''. $params['ext']. '\',\''. $params['info'][0].'\',\''.$params['info'][1].'\');">'.$params['entry'].'</div></td>
		<td width="18%" align="right">'.$params['parsed_size'].'</td>
		<td width="25%">'.$params['parsed_time'].'</td>
		<td width="0px" style="display: none;">'.$params['ext'].'</td>
		<td width="0px" style="display: none;">'.$params['size'].'</td>
		<td width="0px" style="display: none;">'.$params['time'].'</td>
		</tr>';
	}
	
	function table_header($params) 
	{
		return '<table class="sort-table" id="tableHeader" cellspacing="0" width="100%"  border="0" >
		<col />
		<col />
		<col style="text-align: right" />
		<col />
		<thead>
			<tr>
				<td width="4%" id="sortmefirst" onclick="setSortBy(0, false);">'.$params['MY_MESSAGES']['type'].'</td>
				<td width="50%" title="CaseInsensitiveString" onclick="setSortBy(1, false);">'.$params['MY_MESSAGES']['name'].'</td>
				<td width="13%" onclick="setSortBy(2, false);">'.$params['MY_MESSAGES']['size'].'</td>
				<td width="25%" onclick="setSortBy(3, false);">'.$params['MY_MESSAGES']['datemodified'].'</td>
			</tr>
		</thead>
		<tbody style="display: none;"  >
			<tr>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
			</tr>
		</tbody></table>';
	}
	
	function folders_header($params)
	{
		return '<table class="sort-table" id="tableFolders" onselectstart="return false" cellspacing="0" width="100%" border="0" >
		<col />
		<col />
		<col style="text-align: right" />
		<col />
		<col />
		<col />
		<col />
		<thead style="display: none;">
			<tr>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
			</tr>
		</thead>
		<tbody>';
	}
	
	function folders_footer($parms)
	{
		return '</tbody> </table>';
	}
	
	function files_header($params)
	{
		return '<table class="sort-table" id="tableFiles" onselectstart="return false" cellspacing="0" width="100%" border="0" >
		<col />
		<col />
		<col style="text-align: right" />
		<col />
		<col />
		<col />
		<col />
		<thead style="display: none;">
			<tr>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
			</tr>
		</thead>
		<tbody>';
	}
	
	function files_footer($params)
	{
		return '</tbody> </table>';
	}
	function table_footer($params) 
	{
		return '<script type="text/javascript">
		/*<![CDATA[*/
		var st = new SortableTable(document.getElementById("tableHeader"), ["CaseInsensitiveString", "CaseInsensitiveString", "Number", "Number"]);
		var st1 = new SortableTable(document.getElementById("tableFolders"), ["None", "CaseInsensitiveString", "None", "None", "CaseInsensitiveString", "Number", "Number"]);
		var st2 = new SortableTable(document.getElementById("tableFiles"), ["None", "CaseInsensitiveString", "None", "None", "CaseInsensitiveString", "Number", "Number"]);
		var sta = new SelectableTableRows(document.getElementById("tableFolders"), true);
		var stb = new SelectableTableRows(document.getElementById("tableFiles"), true);
		setSortBy(1, true);
		/*]]>*/
		</script>';
	}
}
?>