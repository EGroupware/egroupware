
<!-- vcardin form -->
{vcard_header}
    <form ENCTYPE="multipart/form-data" method="POST" action="{action_url}">
      <table border=0>
      <tr>
       <td>Vcard: <input type="file" name="uploadedfile"></td>
       <td><input type="submit" name="action" value="Load Vcard"></td>
      </tr>
      <tr></tr>
      <tr></tr>
      <tr></tr>
      <tr>
        <td>{lang_access}:</td>
        <td>{lang_groups}:</td>
      </tr>
      <tr>
        <td>
          <select name="access">
		    {access_option}
          </select>
        </td>
        <td colspan="3">
          <select name=n_groups[] multiple size="5">
		    {groups_option}
          </select>
        </td>
      </tr>
      </table>
     </form>
