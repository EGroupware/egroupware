<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<!-- BEGIN login_form -->
<HEAD>

<META http-equiv="Content-Type" content="text/html; charset={charset}">
<META name="AUTHOR" content="phpGroupWare http://www.phpgroupware.org">
<META NAME="description" CONTENT="phpGroupWare login screen">
<META NAME="keywords" CONTENT="phpGroupWare login screen">
<LINK rel="stylesheet" href="/copy.css">
<script language="JavaScript">
<!--
function launchphpgroupware()
{
if (window.screen) {
    var aw = screen.availWidth;
    var ah = screen.availHeight;
    window.moveTo(0, 0);
    window.resizeTo(aw, ah);
    if(window.name != "phpGroupware") 
    {
	Neufenster = window.open("login.php", "phpGroupware","width="+aw+",height="+ah+",location=no,menubar=yes,toolbar=no,resizable=yes");
	Neufenster.focus();
	window.close();
    }
    
  }
}
//-->
</script>

<TITLE>{website_title} - Login</TITLE>
</HEAD>

<BODY bgcolor="#1559a9"  text="#FFFFFF" topmargin="0" marginheight="0" marginwidth="0" leftmargin="0" 
onLoad="launchphpgroupware('')">
<CENTER>{lang_message}</CENTER>
<p>&nbsp;</p>

<center>
<img src=vater_logo.gif border=0>

<br>

Support: <a href="http://linux-at-work.de/index.php"
target="_lawde">http://linux-at-work.de</a>

<br><br><br>

      <FORM method="post" action="{login_url}">
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
     
<!-- END login_form -->
</HTML>
