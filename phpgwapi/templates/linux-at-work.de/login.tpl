<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<!-- BEGIN login_form -->
<HEAD>

<META http-equiv="Content-Type" content="text/html; charset={charset}">
<META name="AUTHOR" content="phpGroupWare http://www.phpgroupware.org">
<META NAME="description" CONTENT="phpGroupWare login screen">
<META NAME="keywords" CONTENT="phpGroupWare login screen">
<STYLE type="text/css">
<!--
	A:link          { color:#FFFFFF; text-decoration:none; }
	A:visited       { color:#FFFFFF; text-decoration:none; }
	A:hover         { color:#CEB1A5; text-decoration:underline; }
	A:active        { color:#FFFFFF; text-decoration:none; }
	
	INPUT
	{
		BORDER-RIGHT: #2B2724 1pt solid;
		BORDER-TOP: #1559a9 1pt solid;
		FONT-SIZE: 11px;
		BORDER-LEFT: #1559a9 1pt solid;
		COLOR: #D1C1B4;
		BORDER-BOTTOM: #2B2724 1pt solid;
		FONT-FAMILY: verdana;
		HEIGHT: 16px;
		BACKGROUND-COLOR: #1559a9
	}

	INPUT.submit
	{
		BORDER-RIGHT: #2B2724 1pt solid;
		BORDER-TOP: #2B2724 1pt solid;
		FONT-SIZE: 11px;
		BORDER-LEFT: #2B2724 1pt solid;
		COLOR: #D1C1B4;
		BORDER-BOTTOM: #2B2724 1pt solid;
		FONT-FAMILY: verdana;
		HEIGHT: 17px;
		BACKGROUND-COLOR: #112244
	}
-->
</STYLE>
<TITLE>{website_title} - Login</TITLE>
</HEAD>

<BODY bgcolor="#1559a9"  text="#FFFFFF" topmargin="0" marginheight="0" marginwidth="0" leftmargin="0">
<CENTER>{lang_message}</CENTER>
<p>&nbsp;</p>

<center>
<img src="/egroupware/phpgwapi/templates/linux-at-work.de/images/vater_logo.gif" border="0">

<br>

Support: <a href="http://linux-at-work.de/index.php"
target="_lawde">http://linux-at-work.de</a>

      <FORM name="login_form" method="post" action="{login_url}">
	<input type="hidden" name="passwd_type" value="text">
       <TABLE border="0" align="CENTER" bgcolor="#1559a9" cellpadding="0" cellspacing="0">
        <TR bgcolor="#1559a9">
         <TD colspan="2" align="CENTER">
          {cd}
         </TD>
        </TR>
        <TR bgcolor="#1559a9">
         <TD colspan="2" align="CENTER">
          &nbsp;
         </TD>
        </TR>
        <TR bgcolor="#1559a9">
         <TD align="left"><font color="#FFFF99">Benutzername:&nbsp;</font></TD>
         <TD><input name="login" value="{cookie}"></TD>
        </TR>
        <TR bgcolor="#1559a9">
         <TD align="left"><font color="#FFFF99">Passwort:&nbsp;</font></TD>
         <TD><input name="passwd" type="password"></TD>
        </TR>
        <TR bgcolor="#1559a9">
         <TD colspan="2" align="CENTER">
          &nbsp;
         </TD>
        </TR>
        <TR bgcolor="#1559a9">
         <TD colspan="2" align="CENTER">
          <input class="submit" type="submit" value="Anmelden" name="submit">
         </TD>
        </TR>
        <TR bgcolor="#1559a9">
         <TD colspan="2" align="CENTER">
          &nbsp;
         </TD>
        </TR>
        <TR bgcolor="#1559a9">
         <TD colspan="2" align="RIGHT">
          <font color="000000" size="-1">basierend auf PHPGroupware {version}</font>
         </TD>
        </TR>       
       </TABLE>
      </FORM>

</center>
<script language="javascript1.2">
<!--
// position cursor in top form field
document.login_form.login.focus();
//-->
</script>
     
<!-- END login_form -->
</HTML>
