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
			    <tr bgcolor="{th_bg}">
			      <td colspan="4"><b>{lang_action}</b></td>
			    </tr>
				
			    <tr bgcolor="{tr_color1}">
			     <td width="25%">{lang_loginid}</td>
			     <td width="25%">{account_lid}&nbsp;</td>
				
			     <td width="25%">{lang_account_active}:</td>
			     <td width="25%">{account_status}</td>
			    </tr>
				
			    <tr bgcolor="{tr_color2}">
			     <td>{lang_firstname}</td>
			     <td>{account_firstname}&nbsp;</td>
			     <td>{lang_lastname}</td>
			     <td>{account_lastname}&nbsp;</td>
			    </tr>
			
			    {password_fields}
			 
			    <tr bgcolor="{tr_color2}">
			     <td>{lang_changepassword}</td>
			     <td>{changepassword}</td>
			     <td>{lang_anonymous}</td>
			     <td>{anonymous}</td>
			    </tr>

			    <tr bgcolor="{tr_color1}">
			     <td>{lang_expires}</td>
			     <td colspan="3">{input_expires}&nbsp;&nbsp;{lang_never}&nbsp;{never_expires}</td>
			    </tr>
	
			    <tr bgcolor="{tr_color2}">
			     <td>{lang_primary_group}</td>
			     <td>{primary_group_select}&nbsp;</td>
			     <td>{lang_groups}</td>
			     <td>{groups_select}&nbsp;</td>
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
    <tr bgcolor="{tr_color1}">
     <td>{lang_password}</td>
     <td><input type="password" name="account_passwd" value="{account_passwd}"></td>
     <td>{lang_reenter_password}</td>
     <td><input type="password" name="account_passwd_2" value="{account_passwd_2}"></td>
    </tr>
<!-- END form_passwordinfo -->

<!-- BEGIN form_buttons_ -->
    <tr bgcolor="{tr_color2}">
     <td colspan="4" align="right"><input type="submit" name="submit" value="{lang_button}"></td>
    </tr>
<!-- END form_buttons_ -->

<!-- BEGIN form_logininfo -->
    <tr bgcolor="{tr_color1}">
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
