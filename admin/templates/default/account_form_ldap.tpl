<!-- BEGIN form -->
{error_messages}

 <form method="POST" action="{form_action}">
  <div align="center"> 
  <table border="0" width="95%">
    <tr>
      <td valign="top">
        {rows}
      </td>
      <td valign="top">
         <table border=0 width=100%>
          <tr class="th">
            <td colspan="4"><b>{lang_action}</b></td>
          </tr>
          <tr class="tr_color1">
           <td width="25%">{lang_loginid}</td>
           <td width="25%">{account_lid}&nbsp;</td>
           <td width="25%">{lang_account_active}:</td>
           <td width="25%">{account_status}</td>
          </tr>
          <tr class="tr_color2">
           <td>{lang_firstname}</td>
           <td>{account_firstname}&nbsp;</td>
           <td>{lang_lastname}</td>
           <td>{account_lastname}&nbsp;</td>
          </tr>
          {password_fields}
          <tr class="tr_color2">
           <td>{lang_homedir}</td>
           <td>{homedirectory}&nbsp;</td>
           <td>{lang_shell}</td>
           <td>{loginshell}&nbsp;</td>
          </tr>
          <tr class="tr_color2">
           <td>{lang_groups}</td>
           <td colspan="3">{groups_select}&nbsp;</td>
          </tr>
          <tr class="tr_color1">
           <td>{lang_expires}</td>
           <td colspan="3">{input_expires}&nbsp;&nbsp;{lang_never}&nbsp;{never_expires}</td>
          </tr>
          {permissions_list}
          {form_buttons}
        </table>
      </td>
    </tr>
  </table>
  </div>
 </form>
<!-- END form -->

<!-- BEGIN form_passwordinfo -->
    <tr class="tr_color1">
     <td>{lang_password}</td>
     <td><input type="password" name="account_passwd" value="{account_passwd}"></td>
     <td>{lang_reenter_password}</td>
     <td><input type="password" name="account_passwd_2" value="{account_passwd_2}"></td>
    </tr>
<!-- END form_passwordinfo -->

<!-- BEGIN form_buttons_ -->
    <tr class="tr_color2">
     <td colspan="2" align="left"><input type="submit" name="submit" value="{lang_button}"></td>
    </form>
    <form method="POST" action="{cancel_action}">
     <td colspan="2" align="right"><input type="submit" name="cancel" value="{lang_cancel}"></td>
    </tr>
<!-- END form_buttons_ -->

<!-- BEGIN form_logininfo -->
    <tr class="tr_color1">
     <td>{lang_lastlogin}</td>
     <td>{account_lastlogin}</td>

     <td>{lang_lastloginfrom}</td>
     <td>{account_lastloginfrom}</td>
    </tr>
<!-- END form_logininfo -->

<!-- BEGIN link_row -->
  <tr class="{tr_color}">
    <td>&nbsp;<a href="{row_link}">{row_text}</a></td>
  </tr>
<!-- END link_row -->
