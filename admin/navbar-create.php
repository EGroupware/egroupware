<?php
  $phpgw_info = array();
  $phpgw_info["flags"]["currentapp"] = "admin";
  $phpgw_info["server"]["site_title"] = "Create a selected navbar image";
  include("../header.inc.php");
?>

This is a utility that will help developers automatically create "selected" navigation bar images.  Currently, it just adds a 1 pixel border around the image in a style that suggests a depressed button.  
<p>
The instructions are as follows:
<ol>
<li>Select an app from the list below.</li>
<li>Right click on the image that appears in your browser and save the image.</li>
<li>Name the image "navbar-sel.gif" -- but without the quotes.</li>
<li>Copy the image to the images subdirectory of the app.</li>
<li>Commit the image to cvs, adding it first if necessary.</li>
</ol>
<p>
<b>NOTE:</b> <i>This app will only work if your server has the GD library compiled into PHP.  Furthermore, if your GD library is too new, it will not work with GIF's, only PNG's...</i>
<p>
<b>NOTE 2:</b> <i>Also, some images seem to give load errors.  This is easily fixed by reexporting them as a GIF from Photoshop in GIF89a format.  Other programs will also work.</i>
<p>
<b>Applications</b>
<p>
<?php
  while (list($key, $val) = each($phpgw_info["apps"])) {
    echo "\n<A HREF=\"".$phpgw->link("/admin/navbar-sel.php","filename=".$phpgw_info["server"]["server_root"]."/".$key."/images/navbar.gif")."\">";
    echo $phpgw_info["apps"][$key]["title"]."</A><BR>";
  }
?>
