<?php
// dummy setup file
if (strpos($_SERVER['PHP_SELF'],'/setup/') === false)
{
        header('Location: /egroupware/setup/');
        exit;
}

