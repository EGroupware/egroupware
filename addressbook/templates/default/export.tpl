<!-- BEGIN export -->
<center>
  <table width="90%">
    <tr bgcolor="{navbar_bg}">
      <td><b><center>{export_text}</center></b>
      </td>
    </tr>
    <tr>
      <td>
        <form enctype="multipart/form-data" action="{action_url}" method="post">
        <ol>
        <li>{lang_select}
        <select name="conv_type">
        <option value="none">&lt;{lang_none}&gt;</option>
{conv}        </select><p></li>
        <li>{filename}:<input name="tsvfilename" value="export.txt"></li>
        <li>{lang_cat}:{cat_link}</li>
        <li><input name="download" type="checkbox" checked>{lang_export_instructions}</li>
        <li><input name="convert" type="submit" value="{download}"></li>
        </ol>
        <input type="hidden" name="sort" value="{sort}">
        <input type="hidden" name="order" value="{order}">
        <input type="hidden" name="filter" value="{filter}">
        <input type="hidden" name="query" value="{query}">
        <input type="hidden" name="start" value="{start}">
        </form>
      </td>
    </tr>
    <tr>
      <td>
        <form action="{cancel_url}" method="post">
        <input type="hidden" name="sort" value="{sort}">
        <input type="hidden" name="order" value="{order}">
        <input type="hidden" name="filter" value="{filter}">
        <input type="hidden" name="query" value="{query}">
        <input type="hidden" name="start" value="{start}">
        <INPUT type="submit" name="Cancel" value="{lang_cancel}">
        </form>
      </td>
    </tr>
  </table>
</center>
<!-- END export -->
