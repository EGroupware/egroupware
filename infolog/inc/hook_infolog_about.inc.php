<?php

  function about_app($tpl,$handle)
  {
     $s = "<b>Info Log</b><p>written by <a href='mailto:RalfBecker@outdoor-training.de'>Ralf Becker</a><br>adopted from todo written by Joseph Engo<p>".
     		 "InfoLog sumarizes the 3 core-programms ToDo, Notes and PhoneLog. InfoLog is based on phpGroupWare's ToDo-List and already has the features ".
			 "of all 3 mentioned applications plus fully working ACL (including Add+Private attributes, add for to addreplys/subtasks), responsibility ".
			 "for a task (ToDo) or a phonecall could be delegated to an other user, all entry could be linked to an addressbook entry and a projekt. ".
			 "This allows you to log all activity of a projekt or address (this should include archiving of emails, faxes and other documents in the ".
			 "future).<br>".
			 "Their is a CSV import filter (under admin) to import existing data  which allows to interactivly assign fields and customize the values ".
			 "with regular expressions and direkt calls to php-functions (e.g. link the phone calls (again) to the addressbook entrys).";
     
     return $s;
  }