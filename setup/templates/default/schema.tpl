<!-- BEGIN header -->
<br />
<div align="center">
<table border="0" width="70%" cellspacing="0" cellpadding="2">
  <tr>
    <td align="center">{description}</td>
  </tr>
</table>
<table border="0" width="70%">
<!-- END header -->

<!-- BEGIN app_header -->
<form method="post" action="{action_url}">
  <tr>
    <td colspan="4" bgcolor="#486591"><center><font color="#fefefe">{appdata}</font></center></td>
    <td colspan="1" bgcolor="#486591"><center><font color="#fefefe">{actions}</font></center></td>
  </tr>
  <tr bgcolor="#99cccc">
    <td colspan="2">{app_info}</td>
    <td align="center">{app_title}</td>
    <td align="center">{app_version}</td>
    <td align="center">{app_install}</td>
  </tr>
<!-- END app_header -->

<!-- BEGIN apps -->
  <tr bgcolor="{bg_color}">
    <td><a href="schematoy.php?detail={appname}"><img src="templates/default/images/{instimg}" alt="{instalt}" border="0" /></a></td>
    <td>{appinfo}&nbsp;</td>
    <td>{apptitle}&nbsp;</td>
    <td align="center"><select name="version[{appname}]">{select_version}</select></td>
    <td bgcolor="#CCFFCC" align="center">{install}</td>
  </tr>
<!-- END apps -->

<!-- BEGIN detail -->
  <tr bgcolor="{bg_color}">
    <td>{name}&nbsp;</td><td>{details}&nbsp;</td>
  </tr>
<!-- END detail -->

<!-- BEGIN table -->
  <tr bgcolor="{bg_color}">
    <td>{tables}</td>
  </tr>
<!-- END table -->

<!-- BEGIN hook -->
  <tr bgcolor="{bg_color}">
    <td>{hooks}</td>
  </tr>
<!-- END hook -->

<!-- BEGIN dep -->
  <tr bgcolor="{bg_color}">
    <td>{deps}</td>
  </tr>
<!-- END dep -->

<!-- BEGIN resolve -->
  <tr bgcolor="{bg_color}">
    <td>{resolution}</td>
  </tr>
<!-- END resolve -->

<!-- BEGIN submit -->
{goback]
<!-- END submit -->

<!-- BEGIN app_footer -->
</table>
<table border="0" width="70%" cellspacing="0" cellpadding="2">
  <tr>
    <td colspan="2" align="center">
      <input type="submit" name="submit" value="{submit}" />
      <input type="submit" name="cancel" value="{cancel}" />
    </td>
  </tr>
</table>
</form>
<!-- END app_footer -->

<!-- BEGIN footer -->
</table>
</div>
<!-- END footer -->
