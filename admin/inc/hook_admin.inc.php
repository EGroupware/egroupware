<?php
{ 

/*
  // This block of code is included by the main Administration page
  // it points to the user, application and other global config
  // pages.
  // $appname is defined in the included file, (=="admin" for this file)
  // $phpgw and $phpgwinfo are also in scope
 
  // Find the icon to display
  echo "<p>\n";
  $imgfile = $phpgw->common->get_image_dir("admin")."/" . $appname .".gif";
  if (file_exists($imgfile)) {
    $imgpath = $phpgw->common->get_image_path("admin")."/" . $appname .".gif";
  } else {
    $imgfile = $phpgw->common->get_image_dir("admin")."/navbar.gif";
    if (file_exists($imgfile)) {
      $imgpath = $phpgw->common->get_image_path("admin")."/navbar.gif";
    } else {
      $imgpath = "";
    }
  }

  // Show the header for the section
  section_start("Administration",$imgpath);
*/

	echo "<p>\n";
	$imgfile = $phpgw->common->get_image_dir($appname) . '/' . $appname . '.gif';
	if (file_exists($imgfile))
	{
		$imgpath = $phpgw->common->get_image_path($appname) . '/' . $appname . '.gif';
	}
	else
	{
		$imgfile = $phpgw->common->get_image_dir($appname) . '/navbar.gif';
		if (file_exists($imgfile))
		{
			$imgpath = $phpgw->common->get_image_path($appname) . '/navbar.gif';
		}
		else
		{
			$imgpath = '';
		}
	}

	section_start(ucfirst($appname),$imgpath);

	// actual items in this section
	echo '<a href="' . $phpgw->link('/admin/accounts.php') . '">' . lang('User accounts')."</a><br>\n";
	echo '<a href="' . $phpgw->link('/admin/groups.php') . '">' . lang('User groups')."</a><br>\n";
	echo "<p>\n";

	echo '<a href="' . $phpgw->link('/admin/applications.php') . '">' . lang('Applications')."</a><br>\n";
	echo "<p>\n";

	echo '<a href="' . $phpgw->link('/admin/mainscreen_message.php') . '">' . lang('Change main screen message') . "</a><br>\n";
	echo "<p>\n";

	echo '<a href="' . $phpgw->link('/admin/currentusers.php') . '">' . lang('View sessions') . "</a><br>\n";
	echo '<a href="' . $phpgw->link('/admin/accesslog.php') . '">' . lang('View Access Log') . "</a><br>\n";

	section_end(); 
}
?>