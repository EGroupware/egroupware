<!-- $Id$ -->
<script type="text/javascript">
	function ExchangeTo(thisform)
	{
		if (opener.document.doit.to.value =='')
		{
			opener.document.doit.to.value = thisform.elements[0].value;
		}
		else
		{
			opener.document.doit.to.value +=","+thisform.elements[0].value;
		}
	}
	function ExchangeCc(thisform)
	{
		if (opener.document.doit.cc.value=='')
		{
			opener.document.doit.cc.value=thisform.elements[0].value;
		}
		else
		{
			opener.document.doit.cc.value+=","+thisform.elements[0].value;
		}
	}
	function ExchangeBcc(thisform)
	{
		if (opener.document.doit.bcc.value=='')
		{
			opener.document.doit.bcc.value=thisform.elements[0].value;
		}
		else
		{
			opener.document.doit.bcc.value+=","+thisform.elements[0].value;
		}
	}	
</script>
<div id="divMain">
<p align="center">{lang_showing}<br>
<table border="0" width="100%">
	<tr valign="top">
		{left}
<form action="{form_action}" name="form" method="POST">
		<td align="left">
			<select name="cat_id" onChange="this.form.submit();"><option value="">{lang_select_cats}</option>{cats_list}</select>
			<noscript>&nbsp;<input type="submit" name="cat" value="{lang_submit}"></noscript>
		</td>
		<td align="center">
			<input type="text" size="10" name="query" value="{query}">
			<input type="submit" name="search" value="{lang_search}">
		</td>
		<td align="right">
			<select name="filter" onChange="this.form.submit();">
				{filter_list}
			</select>
		</td>
</form>
		{right}
	</tr>
</table>
<table border="0" width="100%" cellpadding="2" cellspacing="2">
	<tr class="th">
		<td width="40%" colspan="2">
			{sort_org_name}
		</td>
		<td width="30%" align="center" rowspan="2">
			{lang_email}
		</td>
		<td width="30%" align="center" rowspan="2">
			{lang_hemail}
		</td>
	</tr>
	<tr class="th">
		<td>{sort_n_family}</td>
		<td>{sort_n_given}</td>
	</tr>

  <!-- BEGIN addressbook_list -->
	<tr class="{tr_class}">
		<td colspan="2">{company}</td>
<form>
		<td align="center" rowspan="2">
			<input type="text" style="width: 100%" name="email" value="{email}"><br>
			<input type="button" name="to" value="{to}" onClick="ExchangeTo(this.form);">
			<input type="button" name="cc" value="{cc}" onClick="ExchangeCc(this.form);">
			<input type="button" name="bcc" value="{bcc}" onClick="ExchangeBcc(this.form);">
		</td>
</form>
<form>
		<td align="center" rowspan="2">
			<input type="text" style="width: 100%" name="hemail" value="{hemail}"><br>
			<input type="button" name="h_to" value="{to}" onClick="ExchangeTo(this.form);">
			<input type="button" name="h_cc" value="{cc}" onClick="ExchangeCc(this.form);">
			<input type="button" name="h_bcc" value="{bcc}" onClick="ExchangeBcc(this.form);">
		</td>
</form>
	</tr>
	<tr class="{tr_class}">
		<td>{lastname}</td>
		<td>{firstname}</td>
	</tr>
<!-- END addressbook_list -->

	<tr>
		<td colspan="4" align="center" valign="bottom">
			<br><form><input type="button" name="done" value="{lang_done}" onClick="window.close()"></form>
		</td>
	</tr>
</table>
</div>
</body>
</html>
