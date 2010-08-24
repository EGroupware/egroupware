<!-- BEGIN form -->
{error_messages}

<script>
var email_set=0;
function check_account_email(id)
{
	account   = document.getElementById('account').value;
	firstname = document.getElementById('firstname').value;
	lastname  = document.getElementById('lastname').value;
	email     = document.getElementById('email').value;
	
	if (!email || email_set || id == 'account')
	{
		xajax_doXMLHTTP('admin.uiaccounts.ajax_check_account_email',firstname,lastname,account,{account_id},email_set ? '' : email,id);
		email_set = !email || email_set;
	}
}
function check_password(id)
{
	password  = document.getElementById('password').value;
	password2 = document.getElementById('password2').value;
	
	if (password && (password2 || id == 'password2') && password != password2)
	{
		alert('{lang_passwds_unequal}');
		document.getElementById('password2').value = '';
		document.getElementById('password').select();
		document.getElementById('password').focus();
		return false;
	}
	return true;
}
</script>
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
				
			    <tr class="row_on">
			     <td width="25%">{lang_loginid}</td>
			     <td width="25%">{account_lid}&nbsp;</td>
				
			     <td width="25%">{lang_account_active}:</td>
			     <td width="25%">{account_status}</td>
			    </tr>
				
			    <tr class="row_off">
			     <td>{lang_firstname}</td>
			     <td>{account_firstname}&nbsp;</td>
			     <td>{lang_lastname}</td>
			     <td>{account_lastname}&nbsp;</td>
			    </tr>
			
				{password_fields}

			    <tr class="row_off">
				 <td>{lang_homedir}</td>
				 <td>{homedirectory}&nbsp;</td>
				 <td>{lang_shell}</td>
				 <td>{loginshell}&nbsp;</td>
				</tr>

			    <tr class="row_off">
			     <td>{lang_expires}</td>
			     <td>{input_expires}&nbsp;&nbsp;{lang_never}&nbsp;{never_expires}</td>
			     <td>{lang_email}</td>
			     <td>{account_email}</td>
			    </tr>
 
			    <tr class="row_on">
			     <td>{lang_changepassword}</td>
			     <td>{changepassword}</td>
			     <td>{lang_anonymous}</td>
			     <td>{anonymous}</td>
			    </tr>

			    <tr class="row_off">
			     <td>{lang_groups}</td>
			     <td>{groups_select}&nbsp;</td>
			     <td>{lang_primary_group}</td>
			     <td>{primary_group_select}&nbsp;</td>
			    </tr>
		    
			    <tr class="th">
			     <td>{lang_app}</td>
			     <td>{lang_acl}</td>
			     <td>{lang_app}</td>
			     <td>{lang_acl}</td>
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
    <tr class="row_on">
     <td>{lang_password}</td>
     <td><input type="password" name="account_passwd" value="{account_passwd}"></td>
     <td>{lang_reenter_password}</td>
     <td><input type="password" name="account_passwd_2" value="{account_passwd_2}"></td>
    </tr>
<!-- END form_passwordinfo -->

<!-- BEGIN form_buttons_ -->
    <tr class="row_off">
     <td colspan="4" align="right"><input type="submit" name="submit" value="{lang_button}"></td>
    </tr>
<!-- END form_buttons_ -->

<!-- BEGIN form_logininfo -->
    <tr class="row_on">
     <td>{lang_lastlogin}</td>
     <td>{account_lastlogin}</td>

     <td>{lang_lastloginfrom}</td>
     <td>{account_lastloginfrom}</td>
    </tr>
<!-- END form_logininfo -->

<!-- BEGIN link_row -->
	<tr bgcolor="{tr_color}">
		<td>&nbsp;<a href="{row_link}">{row_text}</a></td>
	</tr>
<!-- END link_row -->
