<!-- $Id$ -->

<!-- BEGIN form -->
<br>
	<form method="POST" action="{action_url}">
		{hidden_vars}
		<table border="0" with="65%" cellpadding="2" cellspacing="2" align="center">
			<tr>
				<td align="center" colspan="2">
					<p><b>{cat_name}</b></p>
					{messages}
				</td>
			</tr>
			<tr>
				<td align="center" colspan="2">{lang_subs}&nbsp;{subs}</td>
			</tr>
			<tr>
<!-- BEGIN delete -->
				<td align="center">
					<input type="submit" name="confirm" value="{lang_yes}">
				</td>
				<td align="center">
					<input type="submit" name="cancel" value="{lang_no}">
				</td>
<!-- END delete -->
<!-- BEGIN done -->
				<td align="center">
					<input type="submit" name="cancel" value="{lang_ok}">
				</td>
<!-- END done -->
			</tr>
		</table>
	</form>

<!-- END form -->
