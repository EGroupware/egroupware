<!-- BEGIN main_navbar -->
<TABLE WIDTH="100%" BORDER="0" CELLPADDING="2" CELLSPACING="0">
	<TR BGCOLOR="{row_on}">
		<TD>
			<table width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td>
						{more}
					</td>
					<TD align=center>
						{message}
					</td>
					<td>
						{trash_link}
					</td>
					<td align=right>
						{select_all_link}
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<FORM name=messageList method=post action="{moveURL}">
	<TR>
		<TD BGCOLOR="{row_off}">
			<TABLE BGCOLOR="{row_off}" COLS=2 BORDER=0 cellpadding=0 cellspacing=0 width=100%>
				<TR>
					<TD WIDTH=40% ALIGN=LEFT VALIGN=CENTER>
						<TT><SMALL>
						<SELECT NAME="targetMailbox" onChange="document.messageList.submit()">
							<OPTION VALUE="-1">{lang_move_selected_to}</option>
							{options_target_mailbox}
						</SELECT></SMALL></TT>
						<noscript>
							<NOBR><SMALL><INPUT TYPE=SUBMIT NAME="moveButton" VALUE="{lang_move}"></SMALL></NOBR>
						</noscript>
						<NOBR><SMALL><INPUT TYPE=checkbox NAME="followCheckBox">{lang_follow}</SMALL></NOBR>
					</TD>
					<TD WIDTH=20% ALIGN=RIGHT>
						{expunge}
						<input type="image" src="{image_path}/sm_read.png" name="mark_read" title="{desc_read}">&nbsp;&nbsp;
						<input type="image" src="{image_path}/sm_unread.png" name="mark_unread" title="{desc_unread}">&nbsp;&nbsp;
						<input type="image" src="{image_path}/sm_important.png" name="mark_flagged" title="{desc_important}">&nbsp;&nbsp;
						<input type="image" src="{image_path}/sm_unimportant.png" name="mark_unflagged" title="{desc_unimportant}">&nbsp;&nbsp;
						<input type="image" src="{image_path}/sm_delete.gif" name="mark_deleted" title="{desc_deleted}">
					</TD>
				</TR>
			</TABLE>
		</TD>
	</TR>
<!-- END main_navbar -->
