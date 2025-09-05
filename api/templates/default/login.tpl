<div id="loginMainDiv" style="background-image:url({background_file})" class="{stock_background_class}">
    <div id="divAppIconBar" style="position:relative;">
        <div id="divLogo">
			<div class="login_logo_container">
				<a href="{logo_url}" target="_blank">
                    <img src="{logo_file}" alt="{logo_title}" title="{logo_title}" id="login_logo"/>
					<!--div style="background-image:url({logo_file})" class="login_logo" border="0" alt="{logo_title}" title="{logo_title}" ></div-->
				</a>
			</div>
		</div>
	    <div id="loginScreenMessage">{lang_message}</div>
    </div>
    <div id="centerBox">
        <div class="egw_message_wrapper">
            <div id="egw_message" class="{cd_class}">
                {cd}
            </div>
            <div class="close bi-x-circle"></div>
        </div>
        <form name="login_form" method="post" action="{login_url}">
            <div class="divLoginbox divSideboxEntry">
                <div class="hiddenCredential">
                    <input type="hidden" name="passwd_type" value="text" />
                    <input type="hidden" name="account_type" value="u" />
                </div>
	            <!-- BEGIN discovery_block -->
	            <div class="centered discovery_block">
                    {discovery}
	            </div>
	            <!-- END discovery_block -->
                <div>
					<span class="username">{lang_username}</span>
					<input name="login" tabindex="4" value="{login}" size="30" aria-label="{lang_username}" {autofocus_login}/>
                </div>
                <div>
					<span class="field_icons password">{lang_password}</span>
					<input name="passwd" tabindex="5" value="{passwd}" type="password" size="30"  alia-label="{lang_password}"/>
                </div>
				<!-- BEGIN 2fa_section -->
				<div class="{2fa_class}">
					<span class="field_icons password">{lang_2fa}</span>
					<input name="2fa_code" tabindex="6" size="30" aria-label="{lang_2fa}" title="{lang_2fa_help}" aria-description="{lang_2fa_help}"/>
				</div>
				<!-- END 2fa_section -->
				<!-- BEGIN remember_me_selection -->
                <div class="remember_me_row">
					<label for="remember_me" title="{lang_remember_me_help}"><span class="field_icons remember_me" style="background-image: none">{lang_remember_me}</span></label>
                    {select_remember_me}
                </div>
                <!-- END remember_me_selection -->
				<!-- BEGIN language_select -->
                <div>
					<span class="field_icons language">{lang_language}</span>
					{select_language}
                </div>
                <!-- END language_select -->
                <!-- BEGIN domain_selection -->
                <div>
						<span class="field_icons domain">{lang_domain}</span>
						{select_domain}
                </div>
                <!-- END domain_selection -->
               <!-- BEGIN change_password -->
                 <div>
						<span class="field_icons password">{lang_new_password}</span>
						<input name="new_passwd" tabindex="7" type="password" size="30" placeholder="{lang_new_password}" {autofocus_new_passwd}/>
                </div>
                <div>
					<span class="field_icons password"></span>
					<input name="new_passwd2" tabindex="8" type="password" placeholder="{lang_repeat_password}" size="30" />
                </div>
               <!-- END change_password -->
                <div class="centered login_button">
                    <input tabindex="9" type="submit" value="  {lang_login}  " aria-label="{lang_login}" name="submitit" />
                </div>

                <!-- BEGIN registration -->
                <div>
	                <div class="registration">{lostpassword_link}{lostid_link}
	                {register_link}</div>
                </div>
                <!-- END registration -->
	            <div>
		            <div id="socialBox"></div>
	            </div>
            </div>
        </form>
    </div>

	<div id="login_footer">
		<div class="apps">
			{footer_apps}
		</div>
	</div>

</div>