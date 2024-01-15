<div id="loginMainDiv">
	<div id="divAppIconBar" style="position:relative;">
		<div id="divLogo"><a href="{logo_url}" target="_blank"><img src="{logo_file}" border="0" alt="{logo_title}" title="{logo_title}" /></a></div>
	</div>
	<div id="centerBox">
		<div id="loginScreenMessage">{lang_message}</div>
		<form name="login_form" method="post" action="{login_url}">
			<table class="divLoginbox divSideboxEntry" cellspacing="0" cellpadding="2" border="0" align="center">
				<tr class="divLoginboxHeader">
					<td colspan="3">{website_title}</td>
				</tr>
				<tr>
					<td colspan="3">
						<div id="loginCdMessage" class="{cd_class}">{cd}</div>
					</td>
				</tr>
				<tr>
					<td colspan="2" height="20">
						<input type="hidden" name="passwd_type" value="text" />
						<input type="hidden" name="account_type" value="u" />
					</td>
					<td rowspan="6">
						<img src="api/templates/default/images/password.png" />
					</td>
				</tr>
				<!-- BEGIN discovery_block -->
				<tr>
					<td>
                        {discovery}
					</td>
				</tr>
				<!-- END discovery_block -->
				<tr>
					<td align="right">{lang_username}:&nbsp;</td>
					<td><input name="login" tabindex="4" value="{login}" size="30" autofocus /></td>
				</tr>
				<tr>
					<td align="right">{lang_password}:&nbsp;</td>
					<td><input name="passwd" tabindex="5" value="{passwd}" type="password" size="30" /></td>
				</tr>
				<!-- BEGIN 2fa_section -->
				<tr class="{2fa_class}">
					<td align="right">{lang_2fa}:&nbsp;</td>
					<td><input name="2fa_code" tabindex="6" size="30" title="{lang_2fa_help}"/></td>
				</tr>
				<!-- END 2fa_section -->
				<!-- BEGIN remember_me_selection -->
				<tr>
					<td align="right">{lang_remember_me}:&nbsp;</td>
					<td>{select_remember_me}</td>
				</tr>
				<!-- END remember_me_selection -->
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
               <!-- BEGIN change_password -->
                 <tr>
                    <td align="right">{lang_new_password}:&nbsp;</td>
                    <td><input name="new_passwd" tabindex="7" type="password" size="30" /></td>
                </tr>
                <tr>
                    <td align="right">{lang_repeat_password}:&nbsp;</td>
                    <td><input name="new_passwd2" tabindex="8" type="password" size="30" /></td>
                </tr>
               <!-- END change_password -->
				<tr>
					<td>&nbsp;</td>
					<td>
						<input tabindex="9" type="submit" value="  {lang_login}  " name="submitit" />
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
				<tr>
					<td id="socialBox" colspan="3"></td>
				</tr>
			</table>
		</form>
	</div>
</div>