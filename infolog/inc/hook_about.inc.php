<?php

  function about_app($tpl,$handle)
  {
     $s = "<b>Info Log</b><p><b>InfoLog</b> written by <a href='mailto:RalfBecker@outdoor-training.de'>Ralf Becker</a><br>".
	  		 "adopted from todo written by Joseph Engo<p>".
     		 "<b>InfoLog</b> sumarizes/replaces the 3 core-applications <b>ToDo</b>, <b>Notes</b> and <b>PhoneLog</b>. ".
			 "<b>InfoLog</b> is based on phpGroupWare's ToDo-List and already has the features of all 3 mentioned ".
			 "applications plus fully working ACL (including Add+Private attributes, add for to addreplys/subtasks) ".
			 "Responsibility for a task (ToDo) or a phonecall could be delegated to an other user, all entries can ".
			 "be linked to an addressbook entry and/or a project. This allows you to log all activity of a ".
			 "contact/address or project. The entries may be viewed or added from InfoLog direct or from within ".
			 "the contact/address or project view.<br>".
			 "It is planed to archive emails, faxes and other documents in InfoLog in the future.<p>".
			 "Their is a CSV import filter (under admin) to import existing data. It allows to interactivly ".
			 "assign fields, customize the values with regular expressions and direct calls to php-functions ".
			 "(e.g. to link the phone calls (again) to the addressbook entrys).";
     
     return $s;
  }
