<p><b>{lang_folder_prefs}</b><hr><p>

<form method="POST" action="{link}">

<table border="0" align="center" cellspacing="1" cellpadding="1" width="60%">
	<tr bgcolor="{th_bg}">
		<td colspan="2">&nbsp;</td>
	</tr>

	<tr bgcolor="{tr_color1}">
		<td nowrap align="right">{lang_when_deleting}:</td>
		<td><TT><SELECT NAME="deleteOptions">
				<option value="move_to_trash" {move_to_trash_selected}>{lang_move_to_trash}</option>
				<option value="mark_as_deleted" {mark_as_deleted_selected}>{lang_mark_as_deleted}</option>
				<option value="remove_immediately" {remove_immediately_selected}>{lang_remove_immediately}</option>
			</SELECT></TT>
		</td>
	</tr>
	<tr bgcolor="{tr_color1}">
		<td nowrap align="right">Trash Folder:</td>
		<td><TT><SELECT NAME="trashFolder">
				{trash_options}
			</SELECT></TT>
		</td>
	</tr>
	<tr bgcolor="{tr_color2}">
		<td nowrap align="right">Sent Folder:</td>
		<td><TT><SELECT NAME="sent">
				{sent_options}
			</SELECT></TT>
		</td>
	</tr>         
	<tr bgcolor="{tr_color1}">
            <td valign=top align=right>
               <br>
               Unseen message notification:
            </td>
            <td>
               <input type=radio name=unseennotify value=1 {notify1_checked}> No notification<br>
               <input type=radio name=unseennotify value=2 {notify2_checked}> Only INBOX<br>
               <input type=radio name=unseennotify value=3 {notify3_checked}> All Folders<br>
               <br>
            </td>
         </tr>
         <tr bgcolor="{tr_color2}">
            <td valign=top align=right>
               <br>
               Unseen message notification type:
            </td>
            <td>
               <input type=radio name=unseentype value=1 {type1_checked}> Only unseen - (4)<br> 
               <input type=radio name=unseentype value=2 {type2_checked}> Unseen and Total - (4/27)
            </td>
         </tr>



        <tr>
                <td colspan="3" align="center">
                        &nbsp;
                </td>
        </tr>
        <tr>
                <td colspan="3" align="center">
                        <input type="submit" name="submit" value="{lang_save}">
                </td>
        </tr>
</table>

</form>