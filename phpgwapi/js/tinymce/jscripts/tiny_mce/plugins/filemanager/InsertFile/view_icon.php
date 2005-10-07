<script language="JavaScript" type="text/JavaScript">

function pviiClassNew(obj, new_style) { //v2.6 by PVII
	obj.className=new_style;
}

function deleteImage(file) 
{
	if(confirm("Delete image \""+file+"\"?")) 
	return true;

	return false;
}
</script>

<?php
class view_icon {

	function view_icon()
	{
		require_once 'Transform.php';
	}
	
	function dir_name($dir) 
	{
		$lastSlash = intval(strrpos($dir, '/'));
		if($lastSlash == strlen($dir)-1){
			return substr($dir, 0, $lastSlash);
		}
		else
		return dirname($dir);
	}
	
	function folder_item($params)
	{
// 		$num_files = num_files($params['absolutePath']);
		return '<td>
		<table width="102" border="0" cellpadding="0" cellspacing="2">
			<tr> 
				<td align="center" class="imgBorder" onMouseOver="pviiClassNew(this,\'imgBorderHover\')" onMouseOut="pviiClassNew(this,\'imgBorder\')">
					<a href="javascript:changeDir('.$params['folderNb'].');" title="'.$params['entry'].'">
					<img src="img/folder.gif" width="80" height="80" border=0 alt="'. $params['entry'].'">
					</a>
				</td>
			</tr>
			<tr> 
				<td><table width="100%" border="0" cellspacing="1" cellpadding="2">
					<tr> 
						<td width="1%" class="buttonOut" onMouseOver="pviiClassNew(this,\'buttonHover\')" onMouseOut="pviiClassNew(this,\'buttonOut\')">
						<!-- <a href="files.php?delFolder='. $params['absolutePath']. '&dir='. $newPath. '" onClick="return deleteFolder(\''. $dir.'\','.
						$num_files. ')"><img src="img/edit_trash.gif" width="15" height="15" border="0"></a></td> -->
						<td width="99%" class="imgCaption">'. $params['entry']. '</td>
					</tr>
				</table></td>
			</tr>
		</table>
		</td>';
	}
	
	function files_item($params)
	{
		
		
		$img_url = $params['MY_BASE_URL']. $params['relativePath'];
		$thumb_image = 'thumbs.php?img='.urlencode($params['relativePath']);//$params['absolutePath']);
		$filesize = $params['parsed_size'];
		$file = $params['entry'];
		$ext = $params['ext'];
		$info = $params['info'];
		return '	
		<td>
			<table width="102" border="0" cellpadding="0" cellspacing="2">
				<tr> 
				<td align="center" class="imgBorder" onMouseOver="pviiClassNew(this,\'imgBorderHover\')" onMouseOut="pviiClassNew(this,\'imgBorder\')">
					<a href="javascript:;" onClick="javascript:fileSelected(\''. $img_url. '\',\''. $file. '\',\''. $ext. '\','.  $info[0]. ','. $info[1]. ');">
					<img src="'. $thumb_image. '" alt="'. $file. ' - '. $filesize. '" border="0"></a></td>
				</tr>
				<tr>
				<td><table width="100%" border="0" cellspacing="0" cellpadding="2">
						<tr> 
							<!-- <td width="1%" class="buttonOut" onMouseOver="pviiClassNew(this,\'buttonHover\')" onMouseOut="pviiClassNew(this,\'buttonOut\')">
							<a href="javascript:;" onClick="javascript:preview(\''. $img_url. '\',\''. $file. '\',\''. $filesize. '\','.
							$info[0].','.$info[1]. ');"><img src="img/edit_pencil.gif" width="15" height="15" border="0"></a></td> 
							<td width="1%" class="buttonOut" onMouseOver="pviiClassNew(this,\'buttonHover\')" onMouseOut="pviiClassNew(this,\'buttonOut\')">
							<a href="files.php?delFile='. $file.' &dir='. $newPath. '" onClick="return deleteImage(\''. $file. '\');"><img src="img/edit_trash.gif" width="15" height="15" border="0"></a></td> -->
							<td colspan="3" width="98%" class="imgCaption">'. $info[0].'x'.$info[1]. '</td>
						</tr>
				</table></td>
				</tr>
			</table>
		</td>';
	}
	
	function files_header($param)
	{
		return "";
	}
	
	function files_footer($param)
	{
		return "";
	}
	
	function folders_header($param)
	{
		return "";
	}

	function folders_footer($param)
	{
		return "";
	}

	function table_header($param) 
	{
		return '<table class="sort-table" id="tableHeader" border="0" cellpadding="0" cellspacing="2"><tr id="sortmefirst">';
	}
	
	function table_footer($param) 
	{
		return '</tr></table>';
	}
}
?>