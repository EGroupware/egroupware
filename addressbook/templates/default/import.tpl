
<!-- BEGIN import -->
<div align="center">
  <table width="90%">
    <tr bgcolor="{navbar_bg}">
      <th colspan="2"><b>{import_text}</b></th>
    </tr>
    <tr>
      <td>
        <table width="85%">
    <tr>
     <td><form enctype="multipart/form-data" action="{action_url}" method="post">
            <ol>
            <li>{lang_import_instructions}{zip_note}</li>
            <li>{lang_exported_file}
              <input name="tsvfile" size="48" type="file" value="{tsvfilename}"><p></li>
            <li>{lang_conv_type}:
            <select name="conv_type">
            <option value="none">&lt;none&gt;</option>
     {conv}
            </select><p></li>
   <li>{lang_cat}:{cat_link}</li>
   <li><input name="private" type="checkbox" value="private" checked>{lang_mark_priv}</li>
            <li><input name="download" type="checkbox" value="{debug}" checked>{lang_debug}</li>
            </ol>
          </tr>
        </table>
      </td>
   </tr>
   <tr>
     <td width="8%">
       <div align="left">
            <input name="convert" type="submit" value="{download}">
            </ol>
              <input type="hidden" name="sort" value="{sort}">
              <input type="hidden" name="order" value="{order}">
              <input type="hidden" name="filter" value="{filter}">
              <input type="hidden" name="query" value="{query}">
              <input type="hidden" name="start" value="{start}">
            </form>
      </td>
     <td width="8%">
        <form action="{cancel_url}" method="post">
        <input type="hidden" name="sort" value="{sort}">
        <input type="hidden" name="order" value="{order}">
        <input type="hidden" name="filter" value="{filter}">
        <input type="hidden" name="query" value="{query}">
        <input type="hidden" name="start" value="{start}">
        <input type="submit" name="Cancel" value="{lang_cancel}">
     </form>
       </div>
     </td>
     <td width="64%">&nbsp;</td>
     <td width="32">&nbsp;</td>
   </tr>
  </table>
</div>
<!-- END import -->
