<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<!-- BEGIN login_form -->
<HEAD>

<META http-equiv="Content-Type" content="text/html; charset={charset}">
<META name="AUTHOR" content="dGroupWare http://www.eGroupWare.org">
<META NAME="description" CONTENT="{website_title} login screen, working environment powered by eGroupWare">
<META NAME="keywords" CONTENT="{website_title} login screen, eGroupWare, groupware, groupware suite">

<TITLE>{website_title} - {lang_login}</TITLE>
</HEAD>

<body bgcolor="#{bg_color}">
<a href="http://{logo_url}"><img src="{logo_file}" alt="{logo_title}" title="{logo_title}" border="0"></a>
<p>&nbsp;</p>
<center>{lang_message}</center>
<p>&nbsp;</p>

<TABLE bgcolor="#000000" border="0" cellpadding="0" cellspacing="0" width="40%" align="center">
 <TR>
  <TD>
   <TABLE border="0" width="100%" bgcolor="#486591" cellpadding="2" cellspacing="1">
    <TR bgcolor="#{bg_color_title}">
     <TD align="LEFT" valign="MIDDLE">
      <font color="#FEFEFE">&nbsp;{website_title}</font>
     </TD>
    </TR>
    <TR bgcolor="#e6e6e6">
     <TD valign="BASELINE">

		<FORM name="login" method="post" action="{login_url}" {autocomplete}>
		<input type="hidden" name="passwd_type" value="text">
			<TABLE border="0" align="CENTER" bgcolor="#486591" width="100%" cellpadding="0" cellspacing="0">
				<TR bgcolor="#e6e6e6">
					<TD colspan="3" align="CENTER">{cd}</TD>
				</TR>
				<TR bgcolor="#e6e6e6">
					<TD align="RIGHT"><font color="#000000">{lang_username}:&nbsp;</font></TD>
					<TD><input name="login" value="{cookie}"></TD>
					<TD>{select_domain}</TD>
				</TR>
				<TR bgcolor="#e6e6e6">
					<TD align="RIGHT"><font color="#000000">{lang_password}:&nbsp;</font></TD>
					<TD><input name="passwd" type="password"></TD>
					<TD>&nbsp;</TD>
				</TR>
				<TR bgcolor="#e6e6e6">
					<TD colspan="3" align="CENTER"><input type="submit" value="{lang_login}" name="submitit"></TD>
				</TR>
				<TR bgcolor="#e6e6e6">
					<TD colspan="3" align="right"><font color="#000000" size="-1">eGroupWare {version}</font></TD>
				</TR>       
			</TABLE>
		</FORM>
     
     </TD>
    </TR>
   </TABLE>
  </TD>
 </TR>
</TABLE>

<!-- END login_form -->
</HTML>
