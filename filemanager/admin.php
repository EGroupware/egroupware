<?

$phpgw_info["flags"] = array("currentapp" => "phpwebhosting");
include("../header.inc.php");
error_reporting (4);

if ($update == 1)
{
	if ($commit)
	{
		$query = sql_query ("SELECT shortcut FROM settings");
		while ($array = mysql_fetch_array ($query))
		{
			$shortcutvar = addslashes ($$array["shortcut"]);
			$query2 = sql_query ("UPDATE settings SET info = '$shortcutvar' WHERE shortcut = '$array[shortcut]'");
			header ("Location: $hostname/admin.php");
		}
	}

	elseif ($set)
	{
		$query = sql_query ("SELECT shortcut FROM settings");
		while ($array = mysql_fetch_array ($query))
		{
			$shortcut = $array["shortcut"];
			$shortcutvar = addslashes ($$shortcut);
			$query2 = sql_query ("UPDATE settings SET original = '$shortcutvar' WHERE shortcut = '$shortcut'");
			$query3 = sql_query ("UPDATE settings SET info = original");
			header ("Location: $hostname/admin.php");
		}
	}

	elseif ($reset)
	{
		$query = sql_query ("UPDATE settings SET info = original");
		header ("Location: $hostname/admin.php");
	}
}

html_page_begin ("Administration"); 
html_page_body_begin ();
html_text_italic ("This is the administration section.  Here you can change most everything.  Be careful, because your changes affect the entire site, including this page.");
html_break (1);
html_text_italic (htmlspecialchars ('"Strings" are arbitrary text included inside of the HTML tags.  For example, "Body String" would be in the <body> tag.  An example would be "text=blue".'));
html_break (1);
html_text_italic ('"Shortcuts" are used mostly by developers');
html_break (2);
html_form_begin ("$hostname/users.php?op=logout");
html_form_input ("submit", NULL, "Log Out");
html_form_end ();
html_form_begin ("$hostname/admin.php?update=1");
html_table_begin ();

$query = sql_query ("SELECT DISTINCT category FROM settings");
while ($cat = mysql_fetch_array ($query))
{
	$cat = $cat["category"];
	html_table_row_begin ();
	html_table_col_begin ();
	html_text_header (2, ucwords ($cat));
	html_table_col_end ();
	html_table_row_end ();

	$query2 = sql_query ("SELECT DISTINCT subcategory FROM settings WHERE category = '$cat'");
	while ($sub = mysql_fetch_array ($query2))
	{
		$sub = $sub["subcategory"];
		html_table_row_begin ();
		html_table_col_begin ();
		html_table_col_end ();
		html_table_col_begin ();
		html_text_header (3, ucwords ($sub));	
		html_table_col_end ();
		html_table_row_end ();
		
		$query3 = sql_query ("SELECT DISTINCT subsubcategory FROM settings WHERE category = '$cat' AND subcategory = '$sub'");
		while ($subsub = mysql_fetch_array ($query3)) 
		{
			$subsub = $subsub["subsubcategory"];
			html_table_row_begin ();
			html_table_col_begin ();
			html_table_col_end ();
			html_table_col_begin ();
			html_table_col_end ();
			html_table_col_begin ();
			html_text_header (4, ucwords ($subsub));
			html_table_col_end ();
			html_table_row_end ();

			$query4 = sql_query ("SELECT * FROM settings WHERE category = '$cat' AND subcategory = '$sub' AND subsubcategory = '$subsub'");
			while ($settings = mysql_fetch_array ($query4))
			{
				$desc = htmlspecialchars ($settings["description"]);
				$original = htmlspecialchars ($settings["original"]);
				if (($original == NULL || !$original) && !is_int ($original))
					$original = "None";
				$info = $settings["info"];
				$shortcut = $settings["shortcut"];
				html_table_row_begin ();
				html_table_col_begin ();
				html_table_col_end ();
				html_table_col_begin ();
				html_table_col_end ();
				html_table_col_begin ();
				html_table_col_end ();
				html_table_col_begin ();
				html_text_underline (ucwords ($desc));
				html_font_set (2);
				html_break (1, html_nbsp (3, 1));
				html_text ("Shortcut: " . $shortcut);
				html_break (1, html_nbsp (3, 1));
				html_text ("Default: " . $original);
				html_break (1, html_nbsp (3, 1));
				html_font_end ();
				html_form_textarea ($shortcut, 5, 50, $info);
				html_table_col_end ();
				html_table_row_end ();
			}
		}
	}
		
}

html_table_end ();
html_break (2);
html_form_input ("submit", "commit", "Commit changes");
html_nbsp (10);
html_form_input ("submit", "set", "Save changes as Defaults");
html_nbsp (10);
html_form_input ("reset", NULL, "Reset to Session Defaults");
html_nbsp (10);
html_form_input ("submit", "reset", "Reset to Saved Defaults");
html_form_end ();
html_page_close ();

?>
