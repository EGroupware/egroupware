<?php

function html_form_begin ($action, $method = "post", $enctype = NULL, $string = HTML_FORM_BEGIN_STRING, $return = 0)
{
	global $phpgw;
	global $sep;

	$action = string_encode ($action, 1);
	$action = $sep . $action;
	$action = html_link ($action, NULL, 1, 0, 1);

	if ($method == NULL)
		$method = "post";
	$method = "method=$method";
	if ($enctype != NULL && $enctype)
		$enctype = "enctype=$enctype";
	$rstring = "<form action=$action $method $enctype $string>";
	return (eor ($rstring, $return));
}

function html_form_input ($type = NULL, $name = NULL, $value = NULL, $maxlength = NULL, $size = NULL, $checked = NULL, $string = HTML_FORM_INPUT_STRING, $return = 0)
{
	if ($type != NULL && $type)
	{
		if ($type == "checkbox")
		{
			$value = string_encode ($value, 1);
		}
		$type = "type=$type";
	}
	if ($name != NULL && $name)
		$name = "name=\"$name\"";
	if ($value != NULL && $value)
	{
		$value = "value=\"$value\"";
	}
	if (is_int ($maxlength) && $maxlength >= 0)
		$maxlength = "maxlength=$maxlength";
	if (is_int ($size) && $size >= 0)
		$size = "size=$size";
	if ($checked != NULL && $checked)
		$checked = "checked";
	$rstring = "<input $type $name $value $maxlength $size $checked $string>";
	return (eor ($rstring, $return));
}

function html_form_textarea ($name = NULL, $rows = NULL, $cols = NULL, $value = NULL, $string = HTML_FORM_TEXTAREA_STRING, $return = 0)
{
	if ($name != NULL && $name)
		$name = "name=\"$name\"";
	if (is_int ($rows) && $rows >= 0)
		$rows = "rows=$rows";
	if (is_int ($cols) && $cols >= 0)
		$cols = "cols=$cols";
	$rstring = "<textarea $name $rows $cols $string>$value</textarea>";
	return (eor ($rstring, $return));
}

function html_form_select_begin ($name = NULL, $return = 0)
{
	if ($name != NULL && $name)
		$name = "name=$name";
	$rstring = "<select $name>";
	return (eor ($rstring, $return));
}

function html_form_select_end ($return = 0)
{
	$rstring = "</select>";
	return (eor ($rstring, $return));
}

function html_form_option ($value = NULL, $displayed = NULL, $selected = NULL, $return = 0)
{
	if ($value != NULL && $value)
		$value = "value=$value";
	if ($selected != NULL && $selected)
		$selected = "selected";
	$rstring = "<option $value $selected>$displayed</option>";
	return (eor ($rstring, $return));
}

function html_form_end ($return = 0)
{
	$rstring = '</form>';
	return (eor ($rstring, $return));
}

function html_nbsp ($times = 1, $return = 0)
{
	if ($times == NULL)
		$times = 1;
	for ($i = 0; $i != $times; $i++)
	{
		if ($return)
			$rstring .= "&nbsp;";
		else
			echo "&nbsp;";
	}
	if ($return)
		return ($rstring);
}

function html ($string, $times = 1, $return = 0)
{
	for ($i = 0; $i != $times; $i++)
	{
		if ($return)
			$rstring .= $string;
		else
			echo $string;
	}
	if ($return)
		return ($rstring);
}

function html_break ($break, $string = "", $return = 0)
{
	if ($break == 1)
		$break = '<br>';
	if ($break == 2)
		$break = '<p>';
	if ($break == 5)
		$break = '<hr>';
	return (eor ($break . $string, $return));
}

function html_page_begin ($title = NULL, $return = 0)
{
//	$rstring = HTML_PAGE_BEGIN_BEFORE_TITLE . $title . HTML_PAGE_BEGIN_AFTER_TITLE;
	return (eor ($rstring, $return));
}

function html_page_body_begin ($bgcolor = HTML_PAGE_BODY_COLOR, $background = NULL, $text = NULL, $link = NULL, $vlink = NULL, $alink = NULL, $string = HTML_PAGE_BODY_STRING, $return = 0)
{
	if ($bgcolor != NULL && $bgcolor)
		$bgcolor = "bgcolor=$bgcolor";
	if ($background != NULL && $background)
		$background = "background=$background";
	if ($text != NULL && $text)
		$text = "text=$text";
	if ($link != NULL && $link)
		$link = "link=$link";
	if ($vlink != NULL && $vlink)
		$vlink = "vlink=$vlink";
	if ($alink != NULL && $alink)
		$alink = "alink=$alink";
//	$rstring = "<body $bgcolor $background $text $link $vlink $alink $string>";
	return (eor ($rstring, $return));
}

function html_page_body_end ($return = 0)
{
//	$rstring = "</body>";
	return (eor ($rstring, $return));
}

function html_page_end ($return = 0)
{
//	$rstring = "</html>";
	return (eor ($rstring, $return));
}

function html_page_close ()
{
	global $phpgw;
//	html_page_body_end ();
//	html_page_end ();
	$phpgw->common->phpgw_footer ();
	exit;
}
function html_text_bold ($text = NULL, $return = 0, $lang = 0)
{
	if ($lang)
		$text = translate ($text);
	$rstring = "<b>$text</b>";	
	return (eor ($rstring, $return));
}

function html_text_underline ($text = NULL, $return = 0, $lang = 0)
{
	if ($lang)
		$text = translate ($text);
	$rstring = "<u>$text</u>";
	return (eor ($rstring, $return));
}

function html_text_italic ($text = NULL, $return = 0, $lang = 0)
{
	if ($lang)
		$text = translate ($text);
	$rstring = "<i>$text</i>";
	return (eor ($rstring, $return));
}

function html_text_summary ($text = NULL, $size = NULL, $return = 0, $lang = 0)
{
	if ($lang)
		$text = translate ($text);
	$rstring .= html_break (1, NULL, $return);
	$rstring .= html_text_bold ($text, $return);
	$rstring .= html_nbsp (3, $return);
	if ($size != NULL && $size >= 0)
		$rstring .= borkb ($size, 1, $return);

	$rstring = html_encode ($rstring, 1);

	if ($return)
		return ($rstring);
}

function html_text_summary_error ($text = NULL, $text2 = NULL, $size = NULL, $return = 0, $lang = 0)
{
	if ($lang)
		$text = translate ($lang);
	$rstring .= html_text_error ($text, 1, $return);

	if (($text2 != NULL && $text2) || ($size != NULL && $size))
		$rstring .= html_nbsp (3, $return);
	if ($text2 != NULL && $text2)
		$rstring .= html_text_error ($text2, NULL, $return);
	if ($size != NULL && $size >= 0)
		$rstring .= borkb ($size, 1, $return);

	if ($return)
		return ($rstring);
}

function html_font_set ($size = NULL, $color = NULL, $family = NULL, $return = 0)
{
	if ($size != NULL && $size)
		$size = "size=$size";
	if ($color != NULL && $color)
		$color = "color=$color";
	if ($family != NULL && $family)
		$family = "family=$family";

	$rstring = "<font $size $color $family>";
	return (eor ($rstring, $return));
}

function html_font_end ($return = 0)
{
	$rstring = "</font>";
	return (eor ($rstring, $return));
}

function html_text_error ($errorwas = NULL, $break = 1, $return = 0)
{
	if ($break)
		$rstring .= html_break (1, NULL, 1);

	$rstring .= html_font_set (NULL, HTML_TEXT_ERROR_COLOR, NULL, 1);
	$rstring .= html_text_bold (html_text_italic ($errorwas, 1), 1);
	$rstring .= html_font_end (1);
	return (eor ($rstring, $return));
}

function html_page_error ($errorwas = NULL, $title = "Error", $return = 0)
{
	$rstring = html_page_begin ($title, $return);
	$rstring .= html_page_body_begin (HTML_PAGE_BODY_COLOR, $return);
	$rstring .= html_break (2, NULL, $return);
	$rstring .= html_text_error ($errorwas, $return);
	$rstring .= html_page_body_end ($return);
	$rstring .= html_page_end ($return);
	if (!$return)
		html_page_close ();
	else
		return ($rstring);
}

function html_link ($href = NULL, $text = NULL, $return = 0, $encode = 1, $linkonly = 0, $target = NULL)
{
	global $phpgw;
	global $sep;
	global $appname;

	if ($encode)
		$href = string_encode ($href, 1);

	###
	# This decodes / back to normal
	###
	$href = preg_replace ("/%2F/", "/", $href);
	$text = trim ($text);

	/* Auto-detect and don't disturb absolute links */
	if (!preg_match ("|^http(.{0,1})://|", $href))
	{
		$href = $sep . $href;

		/* $phpgw->link requires that the extra vars be passed separately */
		$link_parts = explode ("?", $href, 2);
		$address = $phpgw->link ($link_parts[0], $link_parts[1]);
	}
	else
	{
		$address = $href;
	}

	/* If $linkonly is set, don't add any HTML */
	if ($linkonly)
	{
		$rstring = $address;
	}
	else
	{
		if ($target)
			$target = "target=$target";

		$rstring = "<a href=$address $target>$text</a>";
	}

	return (eor ($rstring, $return));
}

function html_link_back ($return = 0)
{
	global $hostname;
	global $path;
	global $groupinfo;
	global $appname;

	$rstring .= html_link ("$appname/index.php?path=$path", HTML_TEXT_NAVIGATION_BACK_TO_USER, 1);

	return (eor ($rstring, $return));
}

function html_table_begin ($width = NULL, $border = NULL, $cellspacing = NULL, $cellpadding = NULL, $rules = NULL, $string = HTML_TABLE_BEGIN_STRING, $return = 0)
{
	if ($width != NULL && $width)
		$width = "width=$width";
	if (is_int ($border) && $border >= 0)
		$border = "border=$border";
	if (is_int ($cellspacing) && $cellspacing >= 0)
		$cellspacing = "cellspacing=$cellspacing";
	if (is_int ($cellpadding) && $cellpadding >= 0)
		$cellpadding = "cellpadding=$cellpadding";
	if ($rules != NULL && $rules)
		$rules = "rules=$rules";

	$rstring = "<table $width $border $cellspacing $cellpadding $rules $string>";
	return (eor ($rstring, $return));
}

function html_link_email ($address = NULL, $text = NULL, $return = 0, $encode = 1)
{
	if ($encode)
		$href = string_encode ($href, 1);

	$rstring = "<a href=mailto:$address>$text</a>";
	return (eor ($rstring, $return));
}

function html_table_end ($return = 0)
{
	$rstring = "</table>";
	return (eor ($rstring, $return));
}

function html_table_row_begin ($align = NULL, $halign = NULL, $valign = NULL, $bgcolor = NULL, $string = HTML_TABLE_ROW_BEGIN_STRING, $return = 0)
{
	if ($align != NULL && $align)
		$align = "align=$align";
	if ($halign != NULL && $halign)
		$halign = "halign=$halign";
	if ($valign != NULL && $valign)
		$valign = "valign=$valign";
	if ($bgcolor != NULL && $bgcolor)
		$bgcolor = "bgcolor=$bgcolor";
	$rstring = "<tr $align $halign $valign $bgcolor $string>";
	return (eor ($rstring, $return));
}

function html_table_row_end ($return = 0)
{
	$rstring = "</tr>";
	return (eor ($rstring, $return));
}

function html_table_col_begin ($align = NULL, $halign = NULL, $valign = NULL, $rowspan = NULL, $colspan = NULL, $string = HTML_TABLE_COL_BEGIN_STRING, $return = 0)
{
	if ($align != NULL && $align)
		$align = "align=$align";
	if ($halign != NULL && $halign)
		$halign = "halign=$halign";
	if ($valign != NULL && $valign)
		$valign = "valign=$valign";
	if (is_int ($rowspan) && $rowspan >= 0)
		$rowspan = "rowspan=$rowspan";
	if (is_int ($colspan) && $colspan >= 0)
		$colspan = "colspan=$colspan";

	$rstring = "<td $align $halign $valign $rowspan $colspan $string>";
	return (eor ($rstring, $return));
}

function html_table_col_end ($return = 0)
{
	$rstring = "</td>";
	return (eor ($rstring, $return));
}

function html_text ($string, $times = 1, $return = 0, $lang = 0)
{
	global $phpgw;

	if ($lang)
		$string = translate ($string);

	if ($times == NULL)
		$times = 1;
	for ($i = 0; $i != $times; $i++)
	{
		if ($return)
			$rstring .= $string;
		else
			echo $string;
	}
	if ($return)
		return ($rstring);
}

function html_text_header ($size = 1, $string = NULL, $return = 0, $lang = 0)
{
	$rstring = "<h$size>$string</h$size>";
	return (eor ($rstring, $return));
}

function html_align ($align = NULL, $string = HTML_ALIGN_MAIN_STRING, $return = 0)
{
	$rstring = "<p align=$align $string>";
	return (eor ($rstring, $return));
}

function html_image ($src = NULL, $alt = NULL, $align = NULL, $border = NULL, $string = HTML_IMAGE_MAIN_STRING, $return = 0)
{
	if ($src != NULL && $src)
		$src = "src=$src";
	if ($alt != NULL && $alt)
		$alt = "alt=\"$alt\"";
	if ($align != NULL && $align)
		$align = "align=$align";
	if (is_int ($border) && $border >= 0)
		$border = "border=$border";
	$rstring = "<img $src $alt $align $border $string>";
	return (eor ($rstring, $return));
}

function html_help_link ($help_name = NULL, $text = "[?]", $target = "_new", $return = 0)
{
	global $settings;
	global $appname;

	if (!$settings["show_help"])
	{
		return 0;
	}

	$rstring = html_link ("$appname/index.php?op=help&help_name=$help_name", $text, True, 1, 0, $target);

	return (eor ($rstring, $return));
}

?>
