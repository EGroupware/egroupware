<?php
  /**************************************************************************\
  * phpGroupWare API - Translation class for phpgw lang files                *
  * This file written by Miles Lott <milosch@phpgroupware.org>               *
  * and Dan Kuykendall <seek3r@phpgroupware.org>                             *
  * Handles multi-language support using flat files                          *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */

	class phpgw_setup_lang extends phpgw_setup_process
	{
		var $langarray;

		/*!
		@function setup_lang
		@abstract constructor for the class, loads all phrases into langarray
		@param $lang	user lang variable (defaults to en)
		*/
		function phpgw_setup_lang()
		{
			$ConfigLang = $GLOBALS['HTTP_COOKIE_VARS']['ConfigLang'] ? $GLOBALS['HTTP_COOKIE_VARS']['ConfigLang'] : $GLOBALS['HTTP_POST_VARS']['ConfigLang'];

			if(!$ConfigLang)
			{
				$lang = 'en';
			}
			else
			{
				$lang = $ConfigLang;
			}

			$fn = '.' . SEP . 'lang' . SEP . 'phpgw_' . $lang . '.lang';
			if (!file_exists($fn))
			{
				$fn = '.' . SEP . 'lang' . SEP . 'phpgw_en.lang';
			}

			if (file_exists($fn))
			{
				$fp = fopen($fn,'r');
				while ($data = fgets($fp,8000))
				{
					list($message_id,$app_name,$null,$content) = explode("\t",$data);
					if ($app_name == 'setup' || $app_name == 'common' || $app_name == 'all')
					{
						$this->langarray[] = array(
							'message_id' => $message_id,
							'content'    => trim($content)
						);
					}
				}
				fclose($fp);
			}
		}

		/*!
		@function translate
		@abstract Translate phrase to user selected lang
		@param $key  phrase to translate
		@param $vars vars sent to lang function, passed to us
		*/
		function translate($key, $vars=False) 
		{
			if (!$vars)
			{
				$vars = array();
			}

			$ret = $key;

			@reset($this->langarray);
			while(list($null,$data) = @each($this->langarray))
			{
				$lang[strtolower($data['message_id'])] = $data['content'];
			}

			if (isset($lang[strtolower ($key)]) && $lang[strtolower ($key)])
			{
				$ret = $lang[strtolower ($key)];
			}
			else
			{
				$ret = $key.'*';
			}
			$ndx = 1;
			while( list($key,$val) = each( $vars ) )
			{
				$ret = preg_replace( "/%$ndx/", $val, $ret );
				++$ndx;
			}
			return $ret;
		}

		/* Following functions are called for app (un)install */

		/*!
		@function get_langs
		@abstract return array of installed languages, e.g. array('de','en')
		*/
		function get_langs()
		{
			$GLOBALS['phpgw_setup']->db->query("SELECT DISTINCT(lang) FROM lang",__LINE__,__FILE__);
			$langs = array();

			while($GLOBALS['phpgw_setup']->db->next_record())
			{
				/* echo 'HELLO: ' . $GLOBALS['phpgw_setup']->db->f(0); */
				$langs[] = $GLOBALS['phpgw_setup']->db->f(0);
			}
			return $langs;
		}

		/*!
		@function drop_langs
		@abstract delete all lang entries for an application, return True if langs were found
		@param $appname app_name whose translations you want to delete
		*/
		function drop_langs($appname)
		{
			$GLOBALS['phpgw_setup']->db->query("SELECT COUNT(message_id) FROM lang WHERE app_name='$appname'",__LINE__,__FILE__);
			$GLOBALS['phpgw_setup']->db->next_record();
			if($GLOBALS['phpgw_setup']->db->f(0))
			{
				$GLOBALS['phpgw_setup']->db->query("DELETE FROM lang WHERE app_name='$appname'",__LINE__,__FILE__);
				return True;
			}
			return False;
		}

		/*!
		@function add_langs
		@abstract process an application's lang files, calling get_langs() to see what langs the admin installed already
		@param $appname app_name of application to process
		*/
		function add_langs($appname,$force_en=False)
		{
			$langs = $this->get_langs();
			if($force_en && !isinarray('en',$langs))
			{
				$langs[] = 'en';
			}

			$GLOBALS['phpgw_setup']->db->transaction_begin();

			while (list($null,$lang) = each($langs))
			{
				/* echo '<br>Working on: ' . $lang; */
				$appfile = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP . 'phpgw_' . strtolower($lang) . '.lang';
				if(file_exists($appfile))
				{
					/* echo '<br>Including: ' . $appfile; */
					$raw_file = file($appfile);

					while (list($null,$line) = @each($raw_file))
					{
						list($message_id,$app_name,$GLOBALS['phpgw_setup']->db_lang,$content) = explode("\t",$line);
						$message_id = $GLOBALS['phpgw_setup']->db->db_addslashes(chop($message_id));
						/* echo '<br>APPNAME:' . $app_name . ' PHRASE:' . $message_id; */
						$app_name   = $GLOBALS['phpgw_setup']->db->db_addslashes(chop($app_name));
						$GLOBALS['phpgw_setup']->db_lang    = $GLOBALS['phpgw_setup']->db->db_addslashes(chop($GLOBALS['phpgw_setup']->db_lang));
						$content    = $GLOBALS['phpgw_setup']->db->db_addslashes(chop($content));

						$GLOBALS['phpgw_setup']->db->query("SELECT COUNT(*) FROM lang WHERE message_id='$message_id' and lang='"
							. $GLOBALS['phpgw_setup']->db_lang . "'",__LINE__,__FILE__);
						$GLOBALS['phpgw_setup']->db->next_record();

						if ($GLOBALS['phpgw_setup']->db->f(0) == 0)
						{
							if($message_id && $content)
							{
								/* echo "<br>adding - INSERT INTO lang VALUES ('$message_id','$app_name','$phpgw_setup->db_lang','$content')"; */
								$GLOBALS['phpgw_setup']->db->query("INSERT INTO lang VALUES ('$message_id','$app_name','"
									. $GLOBALS['phpgw_setup']->db_lang . "','$content')",__LINE__,__FILE__);
							}
						}
					}
				}
			}
			$GLOBALS['phpgw_setup']->db->transaction_commit();
		}
	}
?>
