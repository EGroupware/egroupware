<!-- BEGIN appheader -->
<p><b>{lang_applist}:</b><hr><p>
<table width="60%" border="0" align="center" cellspacing="1" cellpadding="1">
  <tr>
    <td align="center">Select an application/module, or click 'Show all' to convert all tables.</td>
  </tr>
  <tr>
    <td align="center">The following apps have tables:</td>
  </tr>
</table>
<table width="60%" border="0" align="center" cellspacing="1" cellpadding="1">
<form method="POST" action="{action_url}">
  <tr bgcolor="#DDDDDD">
    <td>&nbsp;</td>
    <td>Name</td>
    <td colspan="2">Title</td>
  </tr>
<!-- END appheader -->

<!-- BEGIN appitem -->
  <tr bgcolor="#EEEEEE">
    <td><input type="radio" name="appname" value="{appname}"></td>
    <td>{appname}&nbsp;</td>
    <td colspan="2">{apptitle}&nbsp;</td>
  </tr>
<!-- END appitem -->

<!-- BEGIN appfooter -->
</table>
<table width="60%" border="0" align="center" cellspacing="1" cellpadding="1">
  <tr>
    <td align="left" width="7%">
      <input type="submit" name="submit" value="{lang_submit}"></td>
    <td align="left" width="7%">
      <input type="submit" name="showall" value="{lang_showall}">
    </td>
	<td><input type="checkbox" name="download" value="1">{select_to_download_file}</td>
  </tr>
</form>
</table>
<!-- END appfooter -->
