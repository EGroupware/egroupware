<!-- BEGIN main -->
<table width="100%" border="0" cellpadding="0" cellspacing="1">
	<tr>
		<th width="210px" class="th">
			{lang_folder_list}
		</th>
		<th class="th">
			{lang_folder_settings}
		</th>
	</tr>
	<tr valign="top">
		<td>
			<form name="folderList" method="post" action="{form_action}">
			<div id="divFolderTree" style="overflow:auto; width:250px; height:400px; margin-bottom: 0px;padding-left: 0px; padding-top:0px; z-index:100; border : 1px solid Silver;">
			{folder_tree}
			</form>
		</td>
		<td valign="top">
			{settings_view}
		</td>
	</tr>
</table>

<!-- END main -->

<!-- BEGIN folder_settings -->
<p>
			<table border="0" width="100%" cellpadding=2 cellspacing=0>
				<tr bgcolor='lightgrey'>
					<td colspan="3" align="center">
						<B class="TableTitle">{lang_Overview}</b>
					</td>
				</tr>
				<tr>
					<td style="width:30%;">
						{lang_imap_server}:
					</td>
					<td style="width:50%;">
						{imap_server}
					</td>
					<td style="width:20%;">
						&nbsp;
					</td>
				</tr>
				<tr>
					<td>
						{lang_foldername}:
					</td>
					<td>
						<span id="folderName">{folderName}</span>
					</td>
					<td align="center">
						<div id="divDeleteButton" style="visibility:hidden;"><button type='button' id="mailboxDeleteButton" onclick='if (confirm("{lang_confirm_delete_folder}")) xajax_doXMLHTTP("felamimail.ajaxfelamimail.deleteFolder",tree.getSelectedItemId())'>{lang_delete}</button></div>
					</td>
				</tr>
				<tr>
					<td align="left">
						{lang_rename_folder}
					</td>
					<td align="left">
						<input type="text" size="30" id="newMailboxName" name="newMailboxName" value="{mailboxNameShort}" oonchange="document.renameMailbox.submit()" disabled>
					</td>
					<td align="center">
						<div id="divRenameButton" style="visibility:hidden;"><button type='button' id="mailboxRenameButton" onclick='xajax_doXMLHTTP("felamimail.ajaxfelamimail.renameFolder",tree.getSelectedItemId(), tree.getParentId(tree.getSelectedItemId()), document.getElementById("newMailboxName").value)'>{lang_rename}</button></div>
					</td>
				</tr>
				<tr>
					<td align="left">
						{lang_move_folder}
					</td>
					<td align="left">
						<input type="text" size="30" id="newMailboxMoveName" name="newMailboxMoveName" value="{mailboxNameShort}" oonchange="document.moveMailbox.submit()" disabled;>
						<a id="aMoveSelectFolder" style="visibility:hidden;" href="#" onclick="javascript:window.open('{folder_select_url}', 'windowName', 'width=400,height=500,toolbar=no,resizable=yes'); return false;">{lang_select}</a>
					</td>
					<td align="center">
						<div id="divMoveButton" style="visibility:hidden;"><button type='button' id="mailboxMoveButton" onclick='xajax_doXMLHTTP("felamimail.ajaxfelamimail.renameFolder",tree.getSelectedItemId(), "", document.getElementById("newMailboxMoveName").value)'>{lang_move}</button></div>
					</td>
				</tr>
				<tr>
					<td align="left">
						{lang_create_subfolder}
					</td>
					<td align="left">
						<input type="text" size="30" id="newSubFolder" name="newSubFolder" oonchange="document.createSubFolder.submit()">
					</td>
					<td align="center">
						<button type='button' onclick='xajax_doXMLHTTP("felamimail.ajaxfelamimail.addFolder",tree.getSelectedItemId(),document.getElementById("newSubFolder").value)'>{lang_create}</button>
					</td>
				</tr>
				<tr>
					<td align="left">
						&nbsp;
					</td>
					<td align="center" colspan="2">
						&nbsp;
					</td>
				</tr>
			</table>
</p>
<p>
<div style="width:650px; text-align:left;">
<input type="checkbox" id="active" name="active" value="1" onclick="onchange_active(this)" {checked_active}>{lang_activateACLManagement}
</div>

			<table border="0" width="100%" cellpadding=2 cellspacing=0>
				<tr bgcolor='lightgrey'>
					<td colspan="3" align="center">
						<B class="TableTitle">{lang_ACL}</b>
					</td>
				</tr>
				<tr>
					<td colspan="3">
						<form id="editACL" name="editACL">
						<span id="aclTable"></span>
					</td>
				</tr>
				<tr>
					<td align="left" colspan="3">
						<input type="checkbox" name="recursive" value="1" id="recursive"> {lang_setrecursively}
					</td>
				</tr>
				<tr>
					<td align="left" colspan="2">
						<button type="button" name="addACL" id="addACL" onclick="javascript:egw_openWindowCentered('{url_addACL}','felamiMailACL','400','150');">{lang_add_acl}</button>
					</td>
					<td align="right">
						<button type="button" name="removeACL" id="removeACL" onClick="javascript:xajax_doXMLHTTP('felamimail.ajaxfelamimail.deleteACL', xajax.getFormValues('editACL'),document.getElementById('recursive').checked);document.getElementById('recursive').checked=false;">{lang_delete}</button>
						</form>
					</td>
				</tr>
			</table>
</p>


<!-- END folder_settings -->

<!-- BEGIN mainFolder_settings -->
			<table border="0" width="100%" cellpadding=2 cellspacing=0>
				<tr class="th">
					<td colspan="3">
						<b>Host: {imap_server}</b>
					</td>
				</tr>
				<tr>
					<td width="150"align="left">
						{lang_create_subfolder}
					</td>
					<td align="center">
						<form action="{form_action}" method="post" name="createSubFolder">
						<input type="text" size="30" name="newSubFolder" onchange="document.createSubFolder.submit()">
					</td>
					<td align="center">
						<input type="submit" value="{lang_create}" name="createSubFolder">&nbsp;
						</form>
					</td>
				</tr>
			</table>
<!-- END mainFolder_settings -->

<!-- BEGIN add_acl -->
	<form id="formAddACL" name="formAddACL">
		<input type='hidden' id='imapClassName' name='imapClassName' value='{imapClassName}'>
		<input type='hidden' id='imapLoginType' name='imapLoginType' value='{imapLoginType}'>
		<input type='hidden' id='imapDomainName' name='imapDomainName' value='{imapDomainName}'>
		<table border="0" width="100%" bgcolor="#FFFFFF">
			<tr class="th">
				<td>
					<b>{lang_name}</b>
				</td>
				<td align='center' width="50%">
					<b>{lang_ACL}</b>
				</td>
			</tr>

			<tr class="row_off">
				<td>
					{accountSelection}
				</td>
				<td align='center' width="50%">
					{aclSelection}
				</td>
			</tr>
			<tr>
				<td>
					<button onClick="javascript:window.close();">
						{lang_cancel}
					</button>
				</td>
				<td align="right">
					{lang_setrecursively} <input type="checkbox" name="recursive" value="1" id="recursive">
					<button type="button" ddisabled="disabled" sstyle="color:silver;" onClick="resetACLAddView();">
						{lang_add}
					</button>
				</td>
			</tr>

		<table>
	</form>
<!-- END add_acl -->
