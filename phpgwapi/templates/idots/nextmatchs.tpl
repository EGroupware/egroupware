<!-- BEGIN nextmatchs -->
 <table width="{table_width}" border="0" bgcolor="{th_bg}" cellspacing="0" cellpadding="0" cols="5">
  <tr>   {left}
   <td align="center" bgcolor="{th_bg}" valign="top" width="92%">{cats_search_filter_data}</td>{right}
  </tr>
 </table>
<br/>

<!-- END nextmatchs -->


<!-- BEGIN filter -->
       <td>
{select}
        <noscript>
         <input type="submit" value="{lang_filter}">
        </noscript>
       </td>
<!-- END filter -->


<!-- BEGIN form -->
<td width="2%" align="{align}" valign="top">
<form method="POST" action="{action}" name="{form_name}">
	{hidden}
	<table border="0" bgcolor="{th_bg}" cellspacing="0" cellpadding="0">
	<tr>
		<td align="{align}">
			<input type="image" src="{img}" border="{border}" alt="{label}" name="start" value="{start}">
		</td>
	</tr>
	</table>
</form>
</td>
<!-- END form -->


<!-- BEGIN icon -->
<td width="2%" align="{align}">&nbsp;{_link}</td>
<!-- END icon -->


<!-- BEGIN link -->
<td width="2%" align="{align}" valign="top">
	<table border="0" bgcolor="{th_bg}" cellspacing="0" cellpadding="0">
	<tr>
		<td align="{align}"><img src="{img}" border="{border}" alt="{label}" hspace="2" /></td>
	</tr>
	</table>
</td>
<!-- END link -->


<!-- BEGIN search -->
       <td>
        <input type="text" name="query" value="{query_value}">&nbsp;{searchby}<input type="submit" name="Search" value="{lang_search}">
       </td>
<!-- END search -->

<!-- BEGIN cats -->
       <td>
        {lang_category}&nbsp;&nbsp;<select name="{cat_field}" onChange="this.form.submit();">
         <option value="0">{lang_all}</option>
         {categories}
        </select>
        <noscript><input type="submit" name="cats" value="{lang_select}"></noscript>
       </td>
<!-- END cats -->

<!-- BEGIN search_filter -->
    <table border="0" bgcolor="{th_bg}" cellspacing="0" cellpadding="0">
     <form method="POST" action="{form_action}" name="filter">
      <input type="hidden" name="filter" value="{filter_value}">
      <input type="hidden" name="qfield" value="{qfield_value}">
      <input type="hidden" name="start" value="{start_value}">
      <input type="hidden" name="order" value="{order_value}">
      <input type="hidden" name="sort" value="{sort_value}">
      <input type="hidden" name="query" value="{query_value}">
      <tr>{search}<td>&nbsp;</td>{filter}
      </tr>
     </form>
    </table>
<!-- END search_filter -->

<!-- BEGIN cats_search_filter -->
    <table border="0" bgcolor="{th_bg}" cellspacing="0" cellpadding="0">
     <form method="POST" action="{form_action}" name="filter">
      <input type="hidden" name="filter" value="{filter_value}">
      <input type="hidden" name="qfield" value="{qfield_value}">
      <input type="hidden" name="start" value="{start_value}">
      <input type="hidden" name="order" value="{order_value}">
      <input type="hidden" name="sort" value="{sort_value}">
      <input type="hidden" name="query" value="{query_value}">
      <tr>{cats}<td>&nbsp;</td>{search}<td>&nbsp;&nbsp;</td>{filter}
      </tr>
     </form>
    </table>
<!-- END cats_search_filter -->

