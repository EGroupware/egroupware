<!-- BEGIN header -->
<script language="JavaScript1.2">
	var folderSelectURL		="{folder_select_url}";
	var displayFileSelectorURL	="{file_selector_url}";
	var composeID			="{compose_id}";

	var activityImagePath		= "{ajax-loader}";
	var fm_compose_langNoAddressSet	= "{lang_no_address_set}";

	self.focus();

	self.name="first_Window";

	function addybook() {
		Window1=window.open('{link_addressbook}',"{lang_search}","width=800,height=600,toolbar=no,scrollbars=yes,status=yes,resizable=yes");
	}

	function attach_window(url) {
		awin = window.open(url,"attach","width=500,height=400,toolbar=no,resizable=yes");
	}

	function check_data()
	{
		return true;
	}
</script>
<center>
<form method="post" name="doit" action="{link_action}" ENCTYPE="multipart/form-data" onsubmit="return check_data();">


<!-- BEGIN attachment -->
<script language="javascript1.2">
// position cursor in top form field
///////////////////////////////////////////////////////////////////////document.doit.{focusElement}.focus();
//sString = document.doit.{focusElement}.innerHTML;
//document.doit.{focusElement}.innerHTML = sString;
</script>


<!-- BEGIN fileSelector -->
<div id="fileSelectorDIV1" style="height:80px; border:0px solid red; background-color:white; padding:0px; margin:0px;">
<form method="post" enctype="multipart/form-data" name="fileUploadForm" action="{file_selector_url}">
	<table style="width:99%;">
		<tr>
			<td style="text-align:center;">
				<span id="statusMessage">&nbsp;</span>
			</td>
		</tr>
		<tr>
			<td style="text-align:center;">
				<input id="addFileName" name="addFileName" size="50" sstyle="width:450px;" type="file" onchange="submit()"/>
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
