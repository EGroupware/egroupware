<?php
// PHP Upload Script for CKEditor:  http://coursesweb.net/

// HERE SET THE PATH TO THE FOLDER WITH IMAGES ON YOUR SERVER (RELATIVE TO THE ROOT OF YOUR WEBSITE ON SERVER)
$upload_dir = 'egroupware/webdav.php/home/sysop/pics/profile/';

// HERE PERMISSIONS FOR IMAGE
$imgsets = array(
 'maxsize' => 2000,          // maximum file size, in KiloBytes (2 MB)
 'maxwidth' => 900,          // maximum allowed width, in pixels
 'maxheight' => 800,         // maximum allowed height, in pixels
 'minwidth' => 10,           // minimum allowed width, in pixels
 'minheight' => 10,          // minimum allowed height, in pixels
 'type' => array('bmp', 'gif', 'jpg', 'jpe', 'png')        // allowed extensions
);

$re = '';

if(isset($_FILES['upload']) && strlen($_FILES['upload']['name']) > 1) {
  $upload_dir = trim($upload_dir, '/') .'/';
  $img_name = basename($_FILES['upload']['name']);

  // get protocol and host name to send the absolute image path to CKEditor
  $protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
  $site = $protocol. $_SERVER['SERVER_NAME'] .'/';

  $uploadpath = $_SERVER['DOCUMENT_ROOT'] .'/'. $upload_dir . $img_name;       // full file path
  $sepext = explode('.', strtolower($_FILES['upload']['name']));
  $type = end($sepext);       // gets extension
  list($width, $height) = getimagesize($_FILES['upload']['tmp_name']);     // gets image width and height
  $err = '';         // to store the errors

  // Checks if the file has allowed type, size, width and height (for images)
  if(!in_array($type, $imgsets['type'])) $err .= 'The file: '. $_FILES['upload']['name']. ' has not the allowed extension type.';
  if($_FILES['upload']['size'] > $imgsets['maxsize']*1000) $err .= '\\n Maximum file size must be: '. $imgsets['maxsize']. ' KB.';
  if(isset($width) && isset($height)) {
    if($width > $imgsets['maxwidth'] || $height > $imgsets['maxheight']) $err .= '\\n Width x Height = '. $width .' x '. $height .' \\n The maximum Width x Height must be: '. $imgsets['maxwidth']. ' x '. $imgsets['maxheight'];
    if($width < $imgsets['minwidth'] || $height < $imgsets['minheight']) $err .= '\\n Width x Height = '. $width .' x '. $height .'\\n The minimum Width x Height must be: '. $imgsets['minwidth']. ' x '. $imgsets['minheight'];
  }

  // If no errors, upload the image, else, output the errors
  if($err == '') {
    if(move_uploaded_file($_FILES['upload']['tmp_name'], $uploadpath)) {
      $CKEditorFuncNum = $_GET['CKEditorFuncNum'];
      $url = $site. $upload_dir . $img_name;
      $message = $img_name .' successfully uploaded: \\n- Size: '. number_format($_FILES['upload']['size']/1024, 3, '.', '') .' KB \\n- Image Width x Height: '. $width. ' x '. $height;
      $re = "window.parent.CKEDITOR.tools.callFunction($CKEditorFuncNum, '$url', '$message')";
    }
    else $re = 'alert("Unable to upload the file")';
  }
  else $re = 'alert("'. $err .'")';
}
echo "<script>$re;</script>";