<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<!-- BEGIN login_form -->
<head>

<meta http-equiv="Content-Type" content="text/html; charset={charset}" />
<meta name="AUTHOR" content="dGroupWare http://www.eGroupWare.org" />
<meta name="description" content="{website_title} login screen, working environment powered by eGroupWare" />
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
<meta name="keywords" content="{website_title} login screen, eGroupWare, groupware, groupware suite" />

<title>{website_title} - {lang_login}</title>
</head>

<body bgcolor="#{bg_color}">
<a href="{logo_url}"><img src="{logo_file}" alt="{logo_title}" title="{logo_title}" border="0"></a>
<p>&nbsp;</p>
<center>{lang_message}</center>
<p>&nbsp;</p>

<table bgcolor="#000000" border="0" cellpadding="0" cellspacing="0" width="40%" align="center">
 <tr>
  <td>
   <table border="0" width="100%" bgcolor="#486591" cellpadding="2" cellspacing="1">
    <tr bgcolor="#{bg_color_title}">
     <td align="LEFT" valign="MIDDLE">
      <font color="#FEFEFE">&nbsp;{website_title}</font>
     </td>
    </tr>
    <tr bgcolor="#e6e6e6">
     <td valign="BASELINE">

		<form name="login" method="post" action="{login_url}" {autocomplete}>
		<input type="hidden" name="passwd_type" value="text">
		<input type="hidden" name="account type" value="u">
			<table border="0" align="CENTER" bgcolor="#486591" width="100%" cellpadding="0" cellspacing="0">
				<tr bgcolor="#e6e6e6">
					<td colspan="3" align="CENTER">{cd}</td>
				</tr>
				<tr bgcolor="#e6e6e6">
					<td align="RIGHT"><font color="#000000">{lang_username}:&nbsp;</font></td>
					<td><input name="login" value="{cookie}"></td>
					<td>{select_domain}</td>
				</tr>
				<tr bgcolor="#e6e6e6">
					<td align="RIGHT"><font color="#000000">{lang_password}:&nbsp;</font></td>
					<td><input name="passwd" type="password"></td>
					<td>&nbsp;</td>
				</tr>
				<tr bgcolor="#e6e6e6">
					<td colspan="3" align="CENTER"><input type="submit" value="{lang_login}" name="submitit"></td>
				</tr>
				<tr bgcolor="#e6e6e6">
					<td colspan="3" align="right"><font color="#000000" size="-1">eGroupWare {version}</font></td>
				</tr>
			</table>
		</form>
     
     </td>
    </tr>
   </table>
  </td>
 </tr>
</table>

<!-- END login_form -->
</html>
