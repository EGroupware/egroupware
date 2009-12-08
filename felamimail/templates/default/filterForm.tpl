<!-- BEGIN header -->

<script>
<!--
  self.name="first_Window";
  function addybook()
  {
	Window1=window.open('{link_addressbook}',"{lang_search}","width=800,height=480,toolbar=no,scrollbars=yes,resizable=yes");
  }
  function attach_window(url)
  {
	awin = window.open(url,"attach","width=500,height=400,toolbar=no,resizable=yes");
  }
-->
</script>

<center>
<br>
<form method="post" name="filterForm" action="{link_action}">
<table width="50%" border="0" cellspacing="1" cellpading="1">
<tr>
	<td class="body" width="40%" align="left">
		{lang_filter_name}:
	</td>
	<td class="body" width="60%" colspan="2">
		<input class="body" type=text size="60" name="filterName" value="{filterName}">
	</td>
</tr>
<tr>
	<td class="body" width="40%" align="left">
		{lang_from}:
	</td>
	<td class="body" width="60%" colspan="2">
		<input class="body" type=text size="60" name="from" value="{from}">
	</td>
</tr>
<tr>
	<td class="body" width="40%" align="left">
		{lang_to}:
	</td>
	<td class="body" width="60%" colspan="2">
		<input class="body" type=text size="60" name="to" value="{to}">
	</td>
</tr>
<tr>
	<td class="body" width="10%" align="left">
		{lang_subject}:
	</td>
	<td class="body" colspan="2">
		<input class="body" type=text size="60" name="subject" value='{subject}'>
	</td>
</tr>
<tr>
	<td colspan="3">
		&nbsp;
	</td>
</tr>
<tr>
	<td class="body" width="10%" align="left">
		<a href="{link_newFilter} ">{lang_new_filter}</a>
	</td>
	<td  class="body" align="right" colspan="2">
		<input type="submit" class="body" value="{lang_save}" name="save">
	</td>
</tr>


</table>
</form>

<form method="post" name="filterList" action="{link_action}">

<table width="95%" border="0" cellspacing="1" cellpading="1">
<tr>
	<td class="body">
		{lang_no_filter}
	</td>
	<td class="body" align="right">
		<a href={link_noFilter}>{lang_activate}</a>
	</td>
	<td class="body" align="right">
		&nbsp;
	</td>
	<td class="body" align="right">
		&nbsp;
	</td>
</tr>
{filterrows}
</table>

</form>

<script language="javascript1.2">
<!--
// position cursor in top form field
document.filterForm.filterName.focus();
//-->
</script>
<!-- END header -->

<!-- BEGIN filterrow -->
<tr>
	<td class="body">
		{filtername}
	</td>
	<td class="body" align="right">
		<a href={link_activateFilter}>{lang_activate}</a>
	</td>
	<td class="body" align="right">
		<a href={link_deleteFilter}>{lang_delete}</a>
	</td>
	<td class="body" align="right">
		<a href={link_editFilter}>{lang_edit}</a>
	</td>
</tr>
<!-- END filterrow -->