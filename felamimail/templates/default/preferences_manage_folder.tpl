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
	<tr>
		<td>
			<form name="folderList" method="post" action="{form_action}">
			<div id="divFolderTree" style="overflow:auto; width:210px; height:474px; margin-bottom: 0px;padding-left: 0px; padding-top:0px; z-index:100; border : 1px solid Silver;">
				<table width=100% BORDER="0" style="table-layout:fixed;padding-left:2;">
					<tr>
						<td width="100%" valign="top" nowrap style="font-size:10px">
							{folder_tree}
						</td>
					</tr>
					<tr>
						<td width="100%" valign="bottom" nowrap style="font-size:10px">
							<br>
							<p align="center">
							<small><a href="javascript: d.openAll();">{lang_open_all}</a> | <a href="javascript: d.closeAll();">{lang_close_all}</a></small>
							</p>
						</td>
					</tr>
				</table>
				<input type="hidden" name="mailboxName">
			</div>
			</form>
		</td>
		<td valign="top">
			{settings_view}
		</td>
	</tr>
<!-- 	<tr>
		<td>
			<table border="1" width="100%">
				<tr>
					<td width="100"align="left">
						{lang_quota_status}
					</td>
					<td align="center">
						<table width="100%" border="1">
							<tr>
								<td colspan="2">
									Storage Limit<br>
								</td>
							</tr>
							<tr>
								<td width="50%">
									STORAGE usage level is: 
								</td>
								<td width="50%">
									{storage_usage}
								</td>
							</tr>
							<tr>
								<td>
									STORAGE limit level is: 
								</td>
								<td>
									{storage_limit}
								</td>
							</tr>
							<tr>
								<td colspan="2">
									Message Limit<br>
								</td>
							</tr>
							<tr>
								<td>
									MESSAGE usage level is: 
								</td>
								<td>
									{message_usage}
								</td>
							</tr>
							<tr>
								<td>
									MESSAGE limit level is: 
								</td>
								<td>
									{message_limit}
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr> -->
</table>

<!-- END main -->

<!-- BEGIN folder_settings -->
			<table border="0" width="100%" cellpadding=2 cellspacing=0>
				<tr class="th">
					<td colspan="3">
						<b>Host: {imap_server} Foldername: {folderName}</b>
					</td>
				</tr>
				<tr>
					<td width="150"align="left">
						{lang_folder_status}
					</td>
					<td align="center">
						<form action="{form_action}" method="post" name="subscribeList">
						<input type="radio" name="folderStatus" value="subscribe" onchange="document.subscribeList.submit()" id="subscribed" {subscribed_checked}>
						<label for="subscribed">{lang_subscribed}</label> 
						<input type="radio" name="folderStatus" value="unsubscribe" onchange="document.subscribeList.submit()" id="unsubscribed" {unsubscribed_checked}>
						<label for="unsubscribed">{lang_unsubscribed}</label> 
					</td>
					<td>
						<noscript><input type="submit" value="{lang_update}" name="un_subscribe"></noscript>&nbsp;
						</form>
					</td>
				</tr>
				<tr>
					<td width="150"align="left">
						{lang_rename_folder}
					</td>
					<td align="center">
						<form action="{form_action}" method="post" name="renameMailbox">
						<input type="text" size="30" name="newMailboxName" value="{mailboxNameShort}" onchange="document.renameMailbox.submit()">
					</td>
					<td align="center">
						<input type="submit" value="{lang_rename}" name="renameMailbox">
						</form>
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
				<tr>
					<td width="150"align="left">
						&nbsp;
					</td>
					<td align="center" colspan="2">
						&nbsp;
					</td>
				</tr>
				<tr>
					<td width="150"align="left">
						{lang_delete_folder}
					</td>
					<td>
						&nbsp;
					</td>
					<td align="center">
						<form action="{form_action}" method="post" name="deleteFolder">
						<input type="submit" value="{lang_delete}" name="deleteFolder" onClick="return confirm('{lang_confirm_delete}')">
						</form>
					</td>
				</tr>
			</table>
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

<!-- BEGIN folder_acl -->
			<table width="100%" cellpadding=2 cellspacing=0>
				<tr>
					<td width="50%" align="center">
						<a href="{settings_url}">{lang_folder_settings}</a>
					</td>
					<td width="50%" align="center">
						{lang_folder_acl}
					</td>
				</tr>
			</table>
			<table border="1" width="100%" cellpadding=2 cellspacing=0>
				<tr>
					<td width="150" align="left">
						{lang_username}
					</td>
					<td align="center">
						<b>{lang_acl}</b>
					</td>
					<td align="center">
						<b>{lang_shortcut}</b>
					</td>
				</tr>
				<tr>
					<td width="150" align="left">
						{lang_anyone}
					</td>
					<td align="center">
						A<input type="checkbox" name="acl_a">
						C<input type="checkbox" name="acl_c"> 
						D<input type="checkbox" name="acl_d"> 
						I<input type="checkbox" name="acl_i"> 
						L<input type="checkbox" name="acl_l"> 
						P<input type="checkbox" name="acl_p"> 
						R<input type="checkbox" name="acl_r"> 
						S<input type="checkbox" name="acl_s"> 
						W<input type="checkbox" name="acl_w">
					</td>
					<td align="center">
						<select>
						<option>{lang_reading}</option>
						<option>{lang_writing}</option>
						<option>{lang_posting}</option>
						<option>{lang_none}</option>
						</select>
					</td>
				</tr>
			</table>
<!-- END folder_acl -->
