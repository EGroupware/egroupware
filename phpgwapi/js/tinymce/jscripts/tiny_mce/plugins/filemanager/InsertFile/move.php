<?php
/***********************************************************************
** Title.........:    Insert File Dialog, File Manager
** Version.......:    1.1
** Authors.......:    Al Rashid <alrashid@klokan.sk>
**                    Xiang Wei ZHUO <wei@zhuo.org>
** Filename......:    move.php
** URL...........:    http://alrashid.klokan.sk/insFile/
** Last changed..:    23 July 2004
***********************************************************************/

require('config.inc.php');
require('functions.php');
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
        <head>
        <title>Folder dialog</title>
        <?php
                echo '<meta http-equiv="content-language" content="'.$MY_LANG.'" />'."\n";
                echo '<meta http-equiv="Content-Type" content="text/html; charset='.$MY_CHARSET.'" />'."\n";
                echo '<meta name="author" content="AlRashid, www: http://alrashid.klokan.sk; mailto:alrashid@klokan.sk" />'."\n";
        ?>

<style type="text/css">
 /*<![CDATA[*/
 html, body {  background-color: ButtonFace;  color: ButtonText; font: 11px Tahoma,Verdana,sans-serif; margin: 0; padding: 0;}
body { padding: 5px; }
 .title { background-color: #ddf; color: #000; font-weight: bold; font-size: 120%; padding: 3px 10px; margin-bottom: 10px; border-bottom: 1px  solid black; letter-spacing: 2px;}
select, input, button { font: 11px Tahoma,Verdana,sans-serif; }
.buttons { width: 70px; text-align: center; }
form { padding: 0px;  margin: 0;}
form .elements{
        padding: 10px; text-align: center;
}
 /*]]>*/
 </style>
        <script type="text/javascript" src="js/popup.js"></script>
        <script type="text/javascript">
        /*<![CDATA[*/
                window.resizeTo(550, 200);

                  function onCancel() {
                        __dlg_close(null);
                        return false;
                }

                function onOK() {
                        // pass data back to the calling window
                        var param = new Object();
                        var selection = document.forms[0].newpath;
                        var newDir = selection.options[selection.selectedIndex].value;
                        param['newpath'] = newDir;
                          __dlg_close(param);
                          return false;
                }

                function Init() {
                        __dlg_init();
                }

                function refreshDirs() {
                        var allPaths = document.forms[0].newpath.options;
                        var fields = ["/" <?php dirs($MY_DOCUMENT_ROOT,'');?>];
                        for(i=0; i<fields.length; i++) {
                                var newElem =        document.createElement("OPTION");
                                var newValue = fields[i];
                                newElem.text = newValue;
                                newElem.value = newValue;
                                allPaths.add(newElem);
                        }
                }
/*]]>*/
</script>
</head>
<body onload="Init()">
        <div class="title"><?php echo $MY_MESSAGES['selectfolder']; ?></div>
        <form action="">
                <div class="elements">
                        <label for="newpath">
                                        <?php echo $MY_MESSAGES['directory']; ?>
                        </label>
                        <select name="newpath" id="newpath" style="width:35em">
                        </select>
                </div>
                <div style="text-align: right;">
                         <hr />
                        <button type="button" class="buttons" onclick="return onCancel();"><?php echo $MY_MESSAGES['cancel']; ?></button>
                        <button type="button" class="buttons" onclick="return onOK();"><?php echo $MY_MESSAGES['ok']; ?></button>
                </div>
        </form>
        <script type="text/javascript">
        /*<![CDATA[*/
                refreshDirs();
        /*]]>*/
        </script>
</body>
</html>
