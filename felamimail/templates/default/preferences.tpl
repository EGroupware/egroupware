<p><b>{lang_personal_info}</b><hr><p>

<form method="POST" action="{link}">

<table border="0" align="center" cellspacing="1" cellpadding="1" width="60%">
	<tr bgcolor="{th_bg}">
		<td colspan="2">&nbsp;</td>
	</tr>
	
	<tr bgcolor="{tr_color1}">
		<td align="left">{lang_signature}</td>
		<td align="left">
		<textarea name="signature" rows="4" cols="40">{signature}</textarea>
		</td>
	</tr>
	<tr bgcolor="{tr_color2}">
		<td align="left">&nbsp;</td>
		<td align="left">
		<input type="checkbox" value="1" name="usesignature" {signature_checked}>&nbsp;&nbsp;{lang_enable_sig}
		</td>
	</tr>
	<tr>
		<td colspan="3" align="center">
			<input type="submit" name="submit" value="{lang_save}">
		</td>
	</tr>
                  
</table>

</form>