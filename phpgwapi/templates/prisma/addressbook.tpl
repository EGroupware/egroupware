<!-- $Id$ -->
<script type="text/javascript">
	function Exchange(thisform,field)
	{
		if (opener.document.doit[field].value != '')
		{
			opener.document.doit[field].value += ',';
		}
		opener.document.doit[field].value += thisform.elements.email.value;
	}
</script>
<div id="divMain">
<p align="center">{message}</p>
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
<form action="{form_action}" name="form" method="POST">
		<td width="30%" align="center" rowspan="2">
			{lang_email}<br>
			<input type="submit" name="all[wto]" value="{to}" title="{title_work_to}">
			<input type="submit" name="all[wcc]" value="{cc}" title="{title_work_cc}">
			<input type="submit" name="all[wbcc]" value="{bcc}" title="{title_work_bcc}">
		</td>
		<td width="30%" align="center" rowspan="2">
			{lang_hemail}<br>
			<input type="submit" name="all[hto]" value="{to}" title="{title_home_to}">
			<input type="submit" name="all[hcc]" value="{cc}" title="{title_home_cc}">
			<input type="submit" name="all[hbcc]" value="{bcc}" title="{title_home_bcc}">
		</td>
</form>
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
			<input type="button" name="to" value="{to}" onClick="Exchange(this.form,'to');">
			<input type="button" name="cc" value="{cc}" onClick="Exchange(this.form,'cc');">
			<input type="button" name="bcc" value="{bcc}" onClick="Exchange(this.form,'bcc');">
		</td>
</form>
<form>
		<td align="center" rowspan="2">
			<input type="text" style="width: 100%" name="email" value="{hemail}"><br>
			<input type="button" name="to" value="{to}" onClick="Exchange(this.form,'to');">
			<input type="button" name="cc" value="{cc}" onClick="Exchange(this.form,'cc');">
			<input type="button" name="bcc" value="{bcc}" onClick="Exchange(this.form,'bcc');">
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
