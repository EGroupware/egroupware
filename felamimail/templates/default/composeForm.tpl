<!-- BEGIN header -->
<script language="JavaScript1.2">
	var folderSelectURL		="{folder_select_url}";
	var displayFileSelectorURL	="{file_selector_url}";
	var displayVfsSelectorURL	= "{vfs_selector_url}";
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
		// check recipient(s)
		var tos = document.getElementsByName('address[]');
		for(i=0; i < tos.length; ++i) {
			if (tos[i].value != '') break;
		}
		if (i >= tos.length) {
			alert("{lang_no_recipient}");
			return false;
		}
		// check subject
		var subject = document.getElementById('fm_compose_subject');
		if(subject.value == '') {
			alert("{lang_no_subject}");
			return false;
		}
		return true;
	}
</script>
<center>
<form method="post" name="doit" action="{link_action}" ENCTYPE="multipart/form-data" onsubmit="return check_data();">
<input type="hidden" id="saveAsDraft" name="saveAsDraft" value="0">
<input type="hidden" id="printit" name="printit" value="0">
<TABLE WIDTH="99%" CELLPADDING="1" CELLSPACING="0" style="border: solid #aaaaaa 1px; border-right: solid black 1px; border-bottom: solid black 1px;">
	<tr class="navbarBackground">
		<td align="left" width="270px">
			<div class="parentDIV">
				<button class="menuButton" type="submit" value="{lang_send}" name="send" style="width: 110px; color: black;">
					<img src="{img_mail_send}" style="vertical-align: middle;"> <b>{lang_send}</b>
				</button>
				<button class="menuButton" type="button" onclick="fm_compose_saveAsDraft();" title="{lang_save_as_draft}">
					<img src="{img_fileexport}">
				</button>
				{addressbookButton}
				<button class="menuButton" type="button" onclick="fm_compose_displayFileSelector();" title="{lang_attachments}">
					<img src="{img_attach_file}">
				</button>
				{vfs_attach_button}
				<button class="menuButton" type="button" onclick="fm_compose_printit();" title="{lang_print_it}">
					<img src="{img_print_it}">
				</button>
			</div>
		</td>
		<td align="right">
			<table border="0" width="99%">
					<tr>
						<td>
							<table>
								<tr>
									<td>
										<label for="to_infolog">{lang_save_as_infolog}</label>
									</td>
									<td>
										<label for="to_infolog">{infologImage}</label>
									</td>
									<td>
										{infolog_checkbox}
									</td>
									<td>
										<label for="disposition">{lang_receive_notification}</label>
									</td>
									<td>
										<input type="checkbox" id="disposition" name="disposition" value="1" />
									</td>
								</tr>
							</table>
						</td>
						<td width="150px" align="right">
							{lang_priority}
							<select name="priority">
								<option value="1">{lang_high}</option>
								<option value="3" selected>{lang_normal}</option>
								<option value="5">{lang_low}</option>
							</select>
						</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<table style="clear:left; width:99%;" border="0" cellspacing="0" cellpading="1">
<tr class="row_on">
	<td align="left" style="width:116px;">
		<b>{lang_identity}</b>
	</td>
	<td align="left">
		{select_from}
	</td>
	<td style="width:25px;" valign="bottom">
		&nbsp;
	</td>
</tr>
</table>

<div id="addressDIV" class="row_on" style="width:99%; border: solid black 0px; overflow: auto; padding: 0px; margin: 0px; text-align: left;">
<table id="addressTable" style="width:99%;" border="0" cellspacing="0" cellpading="0"><tbody id="addressRows">{destinationRows}</tbody></table>
</div>

<table style="width:99%;" border="0" cellspacing="0" cellpading="1">
<tr class="row_on">
	<td align="left" style="width:116px;">
		<b>{lang_subject}</b>
	</td>
	<td align="left">
		<input style="width:99%;" id="fm_compose_subject" onkeypress="return keycodePressed(KEYCODE_ENTER);" class="input_text" onkeyup="updateTitle(this.value)" type="text" style="width:450px;" name="subject" value="{subject}" onfocus="startCaptureEventSubjects(this)">
	</td>
	<td style="width:25px;" valign="bottom">
		&nbsp;
	</td>
</tr>
</table>
<div id="resultBox" class="resultBoxHidden"></div>
<!-- END header -->

<!-- BEGIN body_input -->
<table style="width:660px;" border="0" cellspacing="0" cellpading="0">
<tr>
	<td style="width:90px;">
		&nbsp;<br>
	</td>
	<td>
		{errorInfo}<br>
	</td>
</tr>
</table>
<input type="hidden" id="mimeType" name="mimeType" value="{mimeType}">
<div id="editorArea" style="border:0px solid black; width:99%; height:400px;">
	{tinymce}
</div>
<table width="99%" cellspacing="0" cellpadding="0"><tr>
<td>
<fieldset class="bordertop"><legend>{lang_signature}/{lang_stationery}/{lang_editormode}</legend>
	{select_signature} &nbsp; {select_stationery} &nbsp; {toggle_editormode}
<!--		<TEXTAREA class="input_text" NAME=signature ROWS="5" COLS="76" WRAP=HARD>{signature}</TEXTAREA> -->
</fieldset>
</td>
</tr>
</table>
<!-- END body_input -->

<!-- BEGIN simple_text -->
<div id="editorArea">
	<TEXTAREA class="input_text" name="body" style="width:99%; height:100%" wrap="virtual" wrap="soft">{body}</TEXTAREA>
</div>
<!-- END simple_text -->

<!-- BEGIN attachment -->
<script language="javascript1.2">
// position cursor in top form field
///////////////////////////////////////////////////////////////////////document.doit.{focusElement}.focus();
//sString = document.doit.{focusElement}.innerHTML;
//document.doit.{focusElement}.innerHTML = sString;
</script>

<fieldset class="bordertop"><legend>{lang_attachments}</legend>
<div id="divAttachments" style="border:1px solid lightgrey; width:99%;">
<table width="99%" border="0" cellspacing="1" cellpading="0">
{attachment_rows}
</table>
</div>
</fieldset>

</form>
</center>
<!-- END attachment -->

<!-- BEGIN attachment_row -->
<tr bgcolor="{row_color}">
	<td>
		{name}
	</td>
	<td>
		{type}
	</td>
	<td>
		{size}
	</td>
	<td align="center">
		<input type="checkbox" name="attachment[{attachment_number}]" value="{lang_remove}" title="{lang_remove}">
	</td>
</tr>
<!-- END attachment_row -->

<!-- BEGIN attachment_row_bold -->
<tr bgcolor="{th_bg}">
	<td>
		<b>{name}</b>
	</td>
	<td>
		<b>{type}</b>
	</td>
	<td>
		<b>{size}</b>
	</td>
	<td align="center">
		<input class="text" type="submit" name="removefile" value="{lang_remove}">
	</td>
</tr>
<!-- END attachment_row_bold -->

<!-- BEGIN destination_row -->
<tr class="row_on" id="masterRow">
	<td align="right" style="width:90px;">
		{select_destination}
	</td>
	<td style="width:25px;" valign="bottom">
		<span style="display:none;" valign="bottom" class="selectFolder">
			<!-- <div class="divButton" style="background-image: url({img_fileopen});" onclick="fm_compose_selectFolder();" title="{lang_select_folder}"></div> -->
			<button type="button" onclick="fm_compose_selectFolder();" title="{lang_select_folder}" style="border: solid #aaaaaa 1px; border-right: solid black 1px; border-bottom: solid black 1px; font-size:9px; font-weight:bold; height:15px; width:20px; line-height:14px; text-align:center; cursor: pointer;">...</button>
		</span>
	</td>
	<td align="left" valign="bottom"><input class="input_text" onkeypress="return disabledKeyCodes(disabledKeys1);" autocomplete="off" type=text style="width:99%;" name="address[]" value="{address}" onfocus="initResultBox(this)" onblur="stopCapturingEvents()"></td>
	<td style="width:25px;" valign="bottom"><div class="divButton" style="background-image: url({img_clear_left});" onclick="deleteTableRow(this);" title="{lang_remove}"></div></td>
</tr>
<!-- END destination_row -->

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
				<input id="addFileName" name="addFileName" size="50" style="width:450px;" type="file" onchange="fm_compose_addFile()"/>
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
