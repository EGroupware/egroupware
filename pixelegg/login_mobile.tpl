<script src="./api/js/login.js" type="text/javascript"></script>


<div id="loginMainDiv">

    <div id="divAppIconBar" style="position:relative;">
        <div id="divLogo"><a href="{logo_url}" target="_blank"><img src="{logo_file}" border="0" alt="{logo_title}" title="{logo_title}" /></a></div>
    </div>
    <div id="centerBox">
		<div id="loginAvatar">{login_avatar}</div>
        <div id="loginScreenMessage">{lang_message}</div>
        <div id="loginCdMessage" class="{cd_class}">{cd}</div>
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
                    <td rowspan="6">
                        <img src="{template_set}/images/password.svg" class="passwordImage" />
                    </td>
                </tr>
                <!-- BEGIN language_select -->
                <tr>
                    <td>{select_language}</td>
                </tr>
                <!-- END language_select -->
                <!-- BEGIN domain_selection -->
                <tr>
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
                    <td><input name="login" tabindex="4" value="{login}" size="30" {autofocus_login} placeholder="{lang_username}"/></td>
                </tr>
                <tr>
                    <td><input name="passwd" tabindex="5" value="{passwd}" type="password" size="30" placeholder="{lang_password}" /></td>
                </tr>
               <!-- BEGIN change_password -->
                 <tr>
                    <td><input name="new_passwd" tabindex="6" type="password" size="30" {autofocus_new_passwd} placeholder="{lang_new_password}"/></td>
                </tr>
                <tr>
                    <td><input name="new_passwd2" tabindex="7" type="password" size="30" placeholder="{lang_repeat_password}"/></td>
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
