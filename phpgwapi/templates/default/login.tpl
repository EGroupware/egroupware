<!-- BEGIN login_form_standard -->
<A href="http://www.phpgroupware.org"><img src="phpgwapi/templates/{template_set}/images/logo.gif" alt="phpGroupWare"  border="0"></a>
<p>&nbsp;</p>
<CENTER>{phpgw_loginscreen_message}</CENTER>
<p>&nbsp;</p>

<TABLE bgcolor="#000000" border="0" cellpadding="0" cellspacing="0" width="40%" align="CENTER">
 <TR>
  <TD>
   <TABLE border="0" width="100%" bgcolor="#486591" cellpadding="2" cellspacing="1">
    <TR bgcolor="#486591">
     <TD align="LEFT" valign="MIDDLE">
      <font color="#fefefe">&nbsp;phpGroupWare</font>
     </TD>
    </TR>
    <TR bgcolor="#e6e6e6">
     <TD valign="BASELINE">

		<FORM name="login" method="post" action="{login_url}">
		<input type="hidden" name="passwd_type" value="text">
			<TABLE border="0" align="CENTER" bgcolor="#486591" width="100%" cellpadding="0" cellspacing="0">
				<TR bgcolor="#e6e6e6">
					<TD colspan="2" align="CENTER">{phpgw_login_msgbox}</TD>
				</TR>
				<TR bgcolor="#e6e6e6">
					<TD align="RIGHT"><font color="#000000">{lang_username}:&nbsp;</font></TD>
					<TD><input name="login" value="{cookie}"></TD>
				</TR>
				<TR bgcolor="#e6e6e6">
					<TD align="RIGHT"><font color="#000000">{lang_password}:&nbsp;</font></TD>
					<TD><input name="passwd" type="password"></TD>
				</TR>
				<TR bgcolor="#e6e6e6">
					<TD colspan="2" align="CENTER"><input type="submit" value="{lang_login}" name="submitit"></TD>
				</TR>
				<TR bgcolor="#e6e6e6">
					<TD colspan="2" align="RIGHT"><font color="#000000" size="-1">{version}</font></TD>
				</TR>       
			</TABLE>
		</FORM>
     </TD>
    </TR>
   </TABLE>
  </TD>
 </TR>
</TABLE>
<!-- END login_form_standard -->

<!-- BEGIN login_form_select_domain -->
<A href="http://www.phpgroupware.org"><img src="phpgwapi/templates/{template_set}/images/logo.gif" alt="phpGroupWare"  border="0"></a>
<p>&nbsp;</p>
<CENTER>{phpgw_loginscreen_message}</CENTER>
<p>&nbsp;</p>

<TABLE bgcolor="#000000" border="0" cellpadding="0" cellspacing="0" width="50%" align="CENTER">
 <TR>
  <TD>
   <TABLE border="0" width="100%" bgcolor="#486591" cellpadding="2" cellspacing="1">
    <TR bgcolor="#486591">
     <TD align="LEFT" valign="MIDDLE">
      <font color="#fefefe">&nbsp;phpGroupWare</font>
     </TD>
    </TR>
    <TR bgcolor="#e6e6e6">
     <TD valign="BASELINE">

      <FORM method="post" action="{login_url}">
       <TABLE border="0" align="CENTER" bgcolor="#486591" width="100%" cellpadding="0" cellspacing="0">
        <TR bgcolor="#e6e6e6">
         <TD colspan="3" align="CENTER">{phpgw_login_msgbox}</TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD align="RIGHT"><font color="#000000">{lang_username}:</font></TD>
         <TD align="RIGHT"><input name="login" value="{cookie}"></TD>
         <TD align="LEFT">&nbsp;@&nbsp;<select name="logindomain">{select_domain}</select></TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD align="RIGHT"><font color="#000000">{lang_password}:</font></TD>
         <TD align="RIGHT"><input name="passwd" type="password" onChange="this.form.submit()"></TD>
         <TD>&nbsp;</TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD colspan="3" align="CENTER">
          <input type="submit" value="{lang_login}" name="submitit">
         </TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD colspan="3" align="RIGHT">
          <font color="#000000" size="-1">{version}</font>
         </TD>
        </TR>       
       </TABLE>
      </FORM>
     
     </TD>
    </TR>
   </TABLE>
  </TD>
 </TR>
</TABLE>
<!-- END login_form_select_domain -->

<!-- BEGIN login_form_deny -->
<A href="http://www.phpgroupware.org"><img src="phpgwapi/templates/{template_set}/images/logo.gif" alt="phpGroupWare" border="0"></a>
<TABLE border="0" height="94%" width="100%">
 <TR>
  <TD align="CENTER">
    Opps! You caught us in the middle of a system upgrade.<br>Please, check back with us shortly.
  </TD>
 </TR>
</TABLE>
<!-- END login_form_deny -->
