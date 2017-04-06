<script src="./api/js/login.js" type="text/javascript"></script>


<div id="loginMainDiv" style="background-image:url({background_file})">
	<div class="loginMessageBox">
		<div id="loginCdMessage" class="{cd_class}">{cd}</div>
		<span class="closeBtn">X</span>
	</div>
    <div id="divAppIconBar" style="position:relative;">
        <div id="divLogo">
			<div class="login_logo_container">
				<a href="{logo_url}" target="_blank">
					<div style="background-image:url({logo_file})" class="login_logo" border="0" alt="{logo_title}" title="{logo_title}" ></div>
				</a>
			</div>
			<div id="loginScreenMessage">{lang_message}</div>
		</div>
    </div>
    <div id="centerBox">
		<div id="loginAvatar">{login_avatar}</div>

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
				<!-- BEGIN remember_me_selection -->
                 <tr>
                    <td>
						<span class="field_icons remember_me">{lang_remember_me}:</span>
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
						<input name="new_passwd" tabindex="6" type="password" size="30" placeholder="{lang_new_password}" {autofocus_new_passwd}/>
					</td>
                </tr>
                <tr>
                    <td>
						<span class="field_icons password"></span>
						<input name="new_passwd2" tabindex="7" type="password" plcaseholder="{lang_repeat_password}" size="30" />
					</td>
                </tr>
               <!-- END change_password -->
                <tr>
                    <td>
                        <input tabindex="8" type="submit" value="  {lang_login}  " name="submitit" />
                    </td>
                </tr>

                <!-- BEGIN registration -->
                <tr>
                    <td colspan="3" height="20" align="center" class="registration">
                        {lostpassword_link}{lostid_link}{register_link}
                    </td>
                </tr>
                <!-- END registration -->
            </table>
        </form>
    </div>
	<div id="login_footer">
		<div id="socialBox"></div>
		<a href="http://www.egroupware.org" class="logo_footer">
			<img src="api/templates/default/images/login_logo.png">
		</a>
	</div>
</div>
