
<!-- BEGIN addressbook_header -->
<center>{lang_addressbook}
<br>{lang_showing}
<br>{searchreturn}
  <form action="{cats_url}" method="POST">
{cats}{cats_link}
    <input type="hidden" name="sort" value="{sort}">
    <input type="hidden" name="order" value="{order}">
    <input type="hidden" name="filter" value="{filter}">
    <input type="hidden" name="query" value="{query}">
    <input type="hidden" name="start" value="{start}">
    <noscript><input type="submit" name="cats" value="{lang_cats}"></noscript>
  </form>
{search_filter}

<table width=75% border=1 cellspacing=1 cellpadding=3>
<tr bgcolor="{th_bg}">
{cols}
  <td width="3%" height="21">
    <font face="Arial, Helvetica, sans-serif" size="-1">
    {lang_view}
    </font>
  </td>
  <td width="3%" height="21">
    <font face="Arial, Helvetica, sans-serif" size="-1">
    {lang_vcard}
    </font>
  </td>
  <td width="5%" height="21">
    <font face="Arial, Helvetica, sans-serif" size="-1">
    {lang_edit}
    </font>
  </td>
  <td width="5%" height="21">
    <font face="Arial, Helvetica, sans-serif" size="-1">
    {lang_owner}
    </font>
  </td>
</tr>
<!-- END addressbook_header -->
