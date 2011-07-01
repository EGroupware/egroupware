<!-- BEGIN fileSelector -->
<script language="javascript1.2">
	var fileSelectorWindow;
	var importID = "{importid}";
	var fileSelectorWindowTimer;
	var fileSelectorWindowTimeout=500;
	function fm_import_displayVfsSelector() {
		fileSelectorWindow = egw_openWindowCentered('{vfs_selector_url}','fm_import_vfsSelector','640','580',window.outerWidth/2,window.outerHeight/2);
		if(fileSelectorWindowTimer) {
			window.clearTimeout(fileSelectorWindowTimer);
		}
		fileSelectorWindowTimer = window.setInterval('fm_import_reloadImport()', fileSelectorWindowTimeout);
	}

	function fm_import_reloadImport() {
		//searchesPending++;
		//document.title=searchesPending;
		if(fileSelectorWindow.closed == true) {
			window.clearTimeout(fileSelectorWindowTimer);
			xajax_doXMLHTTP("felamimail.ajaxfelamimail.reloadImportMail", importID);
		}
	}
</script>

<div id="fileSelectorDIV1" style="height:80px; border:0px solid red; background-color:white; padding:0px; margin:0px;">
<form method="post" enctype="multipart/form-data" name="fileUploadForm" action="{file_selector_url}">
	<table style="width:99%;">
		<tr>
			<td style="text-align:center;">
				<span id="messages" style="font-size:11px; color:red;">{messages}</span>
			</td>
		</tr>
		<tr>
			<td style="text-align:left;">
				<div style="padding-left:150px;">
					<input type="hidden" size="0" id="newMailboxName" name="newMailboxName" value="" > <!-- this one is needed only to comply with the pop-box -->
					<input type="hidden" size="0" id="importtype" name="importtype" value="{importtype}" >
					<input type="hidden" size="0" id="importid" name="importid" value="{importid}" >
					<input type="text" size="50" id="newMailboxMoveName" name="newMailboxMoveName" value="{mailboxNameShort}" readonly="readonly">
					<a id="aMoveSelectFolder" href="#" onclick="javascript:window.open('{folder_select_url}', 'windowName', 'width=400,height=500,toolbar=no,resizable=yes');
						return false;">{lang_select}</a>
				</div>
			</td>
		</tr>
		<tr>
			<td style="text-align:center;">
				<span id="statusMessage">&nbsp;</span>
			</td>
		</tr>
		<tr>
			<td style="text-align:left;">
				<div style="padding-left:150px;">
					<input id="addFileName" name="addFileName" size="50" sstyle="width:450px;" type="{importtype}" {filebox_readonly} onchange="submit()"/>{vfs_attach_button} &nbsp; {lang_toggleFS}<input id="toggleFS" name="toggleFS" type="checkbox" value='vfs' {toggleFS_preset} onchange="submit()"/>
				</div>
			</td>
		</tr>
		<tr>
			<td style="text-align:center;">
				{lang_max_uploadsize}: {max_uploadsize}
			</td>
		</tr>
	</table>
</form>
</div>
<div id="fileSelectorDIV2" style="position:absolute; display:none; height:80px; width:99%; border:0px solid red; top:0px; left:0px; text-align:right; vertical-align:bottom; background:white;">
<table border="0" style="margin-left:140px; height:100%;"><tr><td><img src="{ajax-loader}"></td><td><span id="statusMessage" style="height:100%; width:99%; text-align:center;border:0px solid green; top:30px; left:0px;">{lang_adding_file_please_wait}</span></td></tr></table>
</div>
<!-- END fileSelector -->
