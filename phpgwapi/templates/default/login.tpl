
<div id="loginMainDiv">
	<div id="divAppIconBar" style="position:relative;">
		<div id="divLogo"><a href="{logo_url}" target="_blank"><img src="{logo_file}" border="0" alt="{logo_title}" title="{logo_title}" /></a></div>
	</div>
	<div id="centerBox">
		<div id="loginScreenMessage">{lang_message}</div>
		<div id="loginCdMessage">{cd}</div>
		<form name="login_form" method="post" action="{login_url}">
			<table class="divLoginbox divSideboxEntry" cellspacing="0" cellpadding="2" border="0" align="center">
				<tr class="divLoginboxHeader">
					<td colspan="3">{website_title}</td>
				</tr>
				<tr>
					<td colspan="2" height="20">
						<input type="hidden" name="passwd_type" value="text" />
						<input type="hidden" name="account_type" value="u" />
					</td>
					<td rowspan="6">
						<img src="phpgwapi/templates/{template_set}/images/password.png" />
					</td>
				</tr>
<!-- BEGIN language_select -->
				<tr>
					<td align="right">{lang_language}:&nbsp;</td>
					<td>{select_language}</td>
				</tr>
<!-- END language_select -->
<!-- BEGIN domain_selection -->
				<tr>
					<td align="right">{lang_domain}:&nbsp;</td>
					<td>{select_domain}</td>
				</tr>
<!-- END domain_selection -->
<!-- BEGIN remember_me_selection -->
				<tr>
					<td align="right">{lang_remember_me}:&nbsp;</td>
					<td>{select_remember_me}</td>
				</tr>
<!-- END remember_me_selection -->
				<tr>
					<td align="right">{lang_username}:&nbsp;</td>
					<td><input name="login" tabindex="4" value="{cookie}" size="30" /></td>
				</tr>
				<tr>
					<td align="right">{lang_password}:&nbsp;</td>
					<td><input name="passwd" tabindex="5" type="password" size="30" /></td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td>
						<input tabindex="6" type="submit" value="  {lang_login}  " name="submitit" />
					</td>
				</tr>
<!-- BEGIN registration -->
				<tr>
					<td colspan="3" height="20" align="center">
						{lostpassword_link}
						{lostid_link}
						{register_link}
					</td>
				</tr>
<!-- END registration -->
			</table>
		</form>
	</div>
</div>
