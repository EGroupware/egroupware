<script src="./api/js/login.js" type="module"></script>


<div id="loginMainDiv" style="background-image:url({background_file})" class="{stock_background_class}">
	<div class="egw_message_wrapper">
		<div id="egw_message" class="{cd_class}">{cd}
		<span class="close"></span></div>
	</div>
    <div id="divAppIconBar" style="position:relative;">
        <div id="divLogo">
			<div class="login_logo_container">
				<a href="{logo_url}" target="_blank">
					<div style="background-image:url({logo_file})" class="login_logo" border="0" alt="{logo_title}" title="{logo_title}" ></div>
				</a>
			</div>
		</div>
	    <div id="loginScreenMessage">{lang_message}</div>
    </div>
    <div id="centerBox">
        <form name="login_form" method="post" action="{login_url}">
            <table class="divLoginbox divSideboxEntry" cellspacing="0" cellpadding="2" border="0" align="center">
                <tr class="divLoginboxHeader">
                    <td colspan="3">{website_title}</td>
                </tr>
                <tr class="hiddenCredential">
                    <td colspan="2" height="20" >
                        <input type="hidden" name="passwd_type" value="text" />
                        <input type="hidden" name="account_type" value="u" />
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
                    <td>
						<span class="field_icons username"></span>
						<input name="login" tabindex="4" value="{login}" size="30" placeholder="{lang_username}" {autofocus_login}/>
					</td>
                </tr>
                <tr>
                    <td>
						<span class="field_icons password"></span>
						<input name="passwd" tabindex="5" value="{passwd}" type="password" size="30" placeholder="{lang_password}"/>
					</td>
                </tr>
				<!-- BEGIN 2fa_section -->
				<tr class="{2fa_class}">
					<td>
						<span class="field_icons password"></span>
						<input name="2fa_code" tabindex="6" size="30" placeholder="{lang_2fa}" title="{lang_2fa_help}"/>
					</td>
				</tr>
				<!-- END 2fa_section -->
				<!-- BEGIN remember_me_selection -->
                <tr>
                    <td class="remember_me_row">
						<label for="remember_me" title="{lang_remember_me_help}"><span class="field_icons remember_me" style="background-image: none">{lang_remember_me}</span></label>
						{select_remember_me}
					</td>
                </tr>
                <!-- END remember_me_selection -->
				<!-- BEGIN language_select -->
                <tr>
                    <td>
						<span class="field_icons language"></span>
						{select_language}
					</td>
                </tr>
                <!-- END language_select -->
                <!-- BEGIN domain_selection -->
                <tr>
                    <td>
						<span class="field_icons domain"></span>
						{select_domain}
					</td>
                </tr>
                <!-- END domain_selection -->
               <!-- BEGIN change_password -->
                 <tr>
                    <td>
						<span class="field_icons password"></span>
						<input name="new_passwd" tabindex="7" type="password" size="30" placeholder="{lang_new_password}" {autofocus_new_passwd}/>
					</td>
                </tr>
                <tr>
                    <td>
						<span class="field_icons password"></span>
						<input name="new_passwd2" tabindex="8" type="password" placeholder="{lang_repeat_password}" size="30" />
					</td>
                </tr>
               <!-- END change_password -->
                <tr>
                    <td>
                        <input tabindex="9" type="submit" value="  {lang_login}  " name="submitit" />
                    </td>
                </tr>

                <!-- BEGIN registration -->
                <tr>
                    <td colspan="3" height="20" align="center" class="registration">
                        {lostpassword_link}{lostid_link}
                    </td>
                </tr>
                <!-- END registration -->
	            <tr>
		            <td align="center">
			            <div id="socialBox"></div>
                        {register_link}
		            </td>
	            </tr>
            </table>
        </form>
    </div>

	<div id="login_footer">
		<div class="apps">
			{footer_apps}
		</div>
	</div>

</div>
