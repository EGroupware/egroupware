<?php
   /**************************************************************************\
   * eGroupWare - Home - Preferences                                          *
   * http://egroupware.org                                                    *
   * Written by Edo van Bruggen <edovanbruggen@raketnet.nl>                   *
   * --------------------------------------------                             *
   *  This program is free software; you can redistribute it and/or modify it *
   *  under the terms of the GNU General Public License as published by the   *
   *  Free Software Foundation; version 2 of the License.                     *
   \**************************************************************************/
   
   
   create_section('Home');

   $prev_img = Array(
	  'no' => lang('Never'),
	  'only_tn' => lang('Only if thumnails exits'),
	  'yes' => lang('Yes')
   );

   create_select_box('Preview thumbs or images in form','prev_img',$prev_img,"When you choose 'Never', only links to the images are displayed; when you choose 'Only if thumnails exists' previews are  shown if an thumbnail of the image exists; if you choose 'Yes' all images are shown");

   $max_prev=array(
	  "1"=>"1",
	  "2"=>"2",
	  "3"=>"3",
	  "4"=>"4",
	  "5"=>"5",
	  "10"=>"10",
	  "20"=>"20",
	  "30"=>"30",
	  "-1"=>lang("No max. number")
   );
   
   
   
  ?> 
