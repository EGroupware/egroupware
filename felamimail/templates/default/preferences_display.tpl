<p><b>{lang_display_prefs}</b><hr><p>

<form method="POST" action="{link}">

<table border="0" align="center" cellspacing="1" cellpadding="1" width="60%">
	<tr bgcolor="{th_bg}">
		<td colspan="2">&nbsp;</td>
	</tr>
	<tr bgcolor="{tr_color1}">
		<td align="right" nowrap>{lang_wrap_at}:&nbsp;</td>
		<td>
         		<tt><input type="text" size="5" name="wrapat" value="{wrapat}"></tt><br>
		</td>
	</tr>
	<tr bgcolor="{tr_color2}">
		<td align="right" nowrap>{lang_size_editor}:&nbsp;</td>
		<td>
			<tt><input type="text" size="5" name="editorsize" value="{editorsize}"></tt><br>            
		</td>
	</tr>
	<tr bgcolor="{tr_color1}">
		<td align="right" nowrap>{lang_location_button}:&nbsp;</td>
		<td>
			<select name="button_new_location">
                		<option value="top" {top_selected}>{lang_option_1}</option>
                		<option value="between" {between_selected}>{lang_option_2}</option>
                		<option value="bottom" {bottom_selected}>{lang_option_3}</option>
                	</select>
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