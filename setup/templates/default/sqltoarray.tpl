<!-- BEGIN sqlheader -->
<table colspan="6">
  <tr bgcolor="#e6e6e6">
    <td>
      <pre>
	$phpgw_baseline = array(
<!-- END sqlheader -->
<!-- BEGIN sqlbody -->
		'{table}' => array(
			'fd' => array(
{arr}			){term}
			'pk' => array({pks}),
			'fk' => array({fks}),
			'ix' => array({ixs}),
			'uc' => array({ucs})
		){term}
<!-- END sqlbody -->
<!-- BEGIN sqlfooter -->
	);
      </pre>
    </td>
  </tr>
<form method="GET" action="{action_url}">
  <tr>
    <td align="left" width="7%">
      <input type="submit" name="download" value="{lang_download}">
      <input type="hidden" name="appname" value="{appname}">
      <input type="hidden" name="table" value="{table}">
      <input type="hidden" name="showall" value="{showall}">
      <input type="hidden" name="submit" value="True">
    </td>
  </tr>
</form>
</table>
</table>
<!-- END sqlfooter -->
