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
			<!-- <div id="divFolderTree" style='width:200;height:200;'></div> -->
			<div id="divFolderTree" style="overflow:auto; width:250px; height:400px; margin-bottom: 0px;padding-left: 0px; padding-top:0px; z-index:100; border : 1px solid Silver;">
			{folder_tree}
			</form>
		</td>
		<td valign="top">
		<table width="100%" border="0" cellspacing="0" cellpading="0" bgcolor="white">
			<tr>
				<th id="tab1" class="activetab" onclick="javascript:tab.display(1);" style="width:50%;"><a href="#" tabindex="0" accesskey="1" onfocus="tab.display(1);" onclick="tab.display(1); return(false);" style="font-size:10px;">{lang_Overview}</a></th>
				<th id="tab2" class="activetab" onclick="javascript:tab.display(2);" style="width:50%;"><a href="#" tabindex="0" accesskey="2" onfocus="tab.display(2);" onclick="tab.display(2); return(false);" style="font-size:10px;">{lang_ACL}</a></th>
			</tr>
		</table>
			{settings_view}
		</td>
	</tr>
</table>

<!-- END main -->

<!-- BEGIN folder_settings -->
		<div id="tabcontent1" class="inactivetab" bgcolor="white">
			<table border="0" width="100%" cellpadding=2 cellspacing=0>
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
		</div>
		<div id="tabcontent2" class="inactivetab">
			<table border="0" width="100%" cellpadding=2 cellspacing=0>
				<tr>
					<td colspan="3">
						<form id="editACL">
						<span id="aclTable"></span>
					</td>
				</tr>
				<tr>
					<td align="left" colspan="3">
						<button type="button" onclick="javascript:egw_openWindowCentered('{url_addACL}','felamiMailACL','400','200');">{lang_add_acl}</button>
						<button type="button" onClick="javascript:xajax_doXMLHTTP('felamimail.ajaxfelamimail.deleteACL', xajax.getFormValues('editACL'));">{lang_delete}</button>
						</form>
					</td>
				</tr>
<tr><td colspan="3">
<style type="text/css">
.CellBody {
	margin-top: 0px;
	margin-bottom: 0px;
}
</style>
<TABLE border=1 style="border: 1px solid black; border-collapse: collapse;">
<CAPTION>
<B class="TableTitle">Mailbox Access Rights </b>
</caption>
<TR>
<TH ROWSPAN="1" COLSPAN="1">
<P CLASS="CellHeading">Access Right</p>

</th>
<TH ROWSPAN="1" COLSPAN="1">

<P CLASS="CellHeading">Purpose</p>

</th>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1" align="center">
<P CLASS="CellBody"><EM CLASS="Emphasis">l</em> </p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Look up the name of the mailbox (but not its contents). </p>

</td>

</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1" align="center">
<P CLASS="CellBody"><EM CLASS="Emphasis">r</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Read the contents of the mailbox. </p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1" align="center">
<P CLASS="CellBody"><EM CLASS="Emphasis">s </em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Preserve the &quot;seen&quot; and &quot;recent&quot; status of messages across IMAP sessions.</p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1" align="center">
<P CLASS="CellBody"><EM CLASS="Emphasis">w</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Write (change message flags such as &quot;recent,&quot; &quot;answered,&quot; and &quot;draft&quot;). </p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1" align="center">

<P CLASS="CellBody"><EM CLASS="Emphasis">i</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Insert (move or copy) a message into the mailbox. </p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1" align="center">
<P CLASS="CellBody"><EM CLASS="Emphasis">p</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">

<P CLASS="CellBody">Post a message in the mailbox by sending the message to the mailbox's submission address (for example, post a message in the <EM CLASS="Filename">cyrushelp</em> mailbox by sending a message to <EM CLASS="Emphasis">sysadmin+cyrushelp@somewhere.net</em>).</p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1" align="center">
<P CLASS="CellBody"><EM CLASS="Emphasis">c</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">

<P CLASS="CellBody">Create a new mailbox below the top-level mailbox (ordinary users cannot create top-level mailboxes).</p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1" align="center">
<P CLASS="CellBody"><EM CLASS="Emphasis">d </em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Delete a message and/or the mailbox itself.</p>

</td>
</tr>

<TR>
<TD ROWSPAN="1" COLSPAN="1" align="center">
<P CLASS="CellBody"><EM CLASS="Emphasis">a</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Administer the mailbox (change the mailbox's ACL).</p>

</td>
</tr>
</table>

<p>
<TABLE border=1 style="border: 1px solid black; border-collapse: collapse;">
<CAPTION>
<B class="TableTitle">Abbreviations for Common Access Rights </b>
</caption>
<TR>
<TH ROWSPAN="1" COLSPAN="1">
<P CLASS="CellHeading">Abbreviation</p>

</th>
<TH ROWSPAN="1" COLSPAN="1">

<P CLASS="CellHeading">Access Rights</p>

</th>
<TH ROWSPAN="1" COLSPAN="1">
<P CLASS="CellHeading">Result</p>

</th>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody"><EM CLASS="Emphasis">none</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">

<P CLASS="CellBody">Blank</p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">The user has no rights whatsoever.</p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody"><EM CLASS="Emphasis">read</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">

<P CLASS="CellBody"><EM CLASS="Emphasis">lrs </em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Allows a user to read the contents of the mailbox.</p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody"><EM CLASS="Emphasis">post</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">

<P CLASS="CellBody"><EM CLASS="Emphasis">lrps</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Allows a user to read the mailbox and post to it through the delivery system by sending mail to the mailbox's submission address. </p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody"><EM CLASS="Emphasis">append</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">

<P CLASS="CellBody"><EM CLASS="Emphasis">lrsip</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Allows a user to read the mailbox and append messages to it, either via IMAP or through the delivery system.</p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody"><EM CLASS="Emphasis">write</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">

<P CLASS="CellBody"><EM CLASS="Emphasis">lrswipcd</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">Allows a user to read the maibox, post to it, append messages to it, and delete messages or the mailbox itself. The only right not given is the right to change the mailbox's ACL. </p>

</td>
</tr>
<TR>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody"><EM CLASS="Emphasis">all</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">

<P CLASS="CellBody"><EM CLASS="Emphasis">lrswipcda</em></p>

</td>
<TD ROWSPAN="1" COLSPAN="1">
<P CLASS="CellBody">The user has all possible rights on the mailbox. This is usually granted to users only on the mailboxes they own.</p>

</td>
</tr>
</table>
</td></tr>
			</table>
		</div>
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
	<form id="formAddACL" >
		<table border="0" width="100%" bgcolor="#FFFFFF">
			<tr class="th">
				<td>
					Name
				</td>
				<td>
					L
				</td>
				<td>
					R
				</td>
				<td>
					S
				</td>
				<td>
					W
				</td>
				<td>
					I
				</td>
				<td>
					P
				</td>
				<td>
					C
				</td>
				<td>
					D
				</td>
				<td>
					A
				</td>
			</tr>

			<tr class="row_off">
				<td>
					<input type="text" name="accountName" id="accountName" style="width:100%;">
				</td>
				<td>
					<input type="checkbox" name="acl[]" value="l" id="acl_l">
				</td>
				<td>
					<input type="checkbox" name="acl[]" value="r" id="acl_r">
				</td>
				<td>
					<input type="checkbox" name="acl[]" value="s" id="acl_s">
				</td>
				<td>
					<input type="checkbox" name="acl[]" value="w" id="acl_w">
				</td>
				<td>
					<input type="checkbox" name="acl[]" value="i" id="acl_i">
				</td>
				<td>
					<input type="checkbox" name="acl[]" value="p" id="acl_p">
				</td>
				<td>
					<input type="checkbox" name="acl[]" value="c" id="acl_c">
				</td>
				<td>
					<input type="checkbox" name="acl[]" value="d" id="acl_d">
				</td>
				<td>
					<input type="checkbox" name="acl[]" value="a" id="acl_a">
				</td>
			</tr>

			<tr>
				<td colspan="4">
					<button onClick="javascript:window.close();">
						{lang_cancel}
					</button>
				</td>
				<td colspan="6" align="right">
					<button type="button" ddisabled="disabled" sstyle="color:silver;" onClick="resetACLAddView();">
						{lang_add}
					</button>
				</td>
			</tr>

		<table>
	</form>
<!-- END add_acl -->
