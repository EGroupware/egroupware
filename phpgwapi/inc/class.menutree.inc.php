<?php  
  /**************************************************************************\
  * phpGroupWare API - MenuTree                                              *
  * This file based on PHP3 TreeMenu                                         *
  * (c)1999 Bjorge Dijkstra <bjorge@gmx.net>                                 *
  * ------------------------------------------------------------------------ *
  * This is not part of phpGroupWare, but is used by phpGroupWare.           * 
  * http://www.phpgroupware.org/                                             * 
  * ------------------------------------------------------------------------ *
  * This program is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU General Public License as published by the    *
  * Free Software Foundation; either version 2 of the License, or (at your   *
  * option) any later version.                                               *
  \**************************************************************************/

  /* $Id$ */

  /*********************************************/
  /*  Settings                                 */
  /*********************************************/
  /*                                           */      
  /*  $treefile variable needs to be set in    */
  /*  main file                                */
  /*                                           */ 
  /*********************************************/

	class menutree
	{
		var $read_from_file;          // You can send the tree info from a string or file
		var $root_level_value;        // This is what the top level name or image will be
		var $last_column_size;

		function menutree($read_from_file='text')
		{
			if($read_from_file == 'text')
			{
				$this->read_from_file = False;
			}
			elseif($read_from_file == 'file')
			{
				$this->read_from_file = True;
			}
			$this->set_lcs();
		}

		function set_lcs($size=300)
		{
			$this->last_column_size = $size;
		}

		function showtree($treefile, $expandlevels='', $num_menus = 50, $invisible_menus = Null)
		{
			$SCRIPT_FILENAME = $GLOBALS['HTTP_SERVER_VARS']['SCRIPT_FILENAME'];
			$REQUEST_URI     = $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'];
    
			$img_expand   = $GLOBALS['phpgw']->common->image('phpgwapi','tree_expand.gif');
			$img_collapse = $GLOBALS['phpgw']->common->image('phpgwapi','tree_collapse.gif');
			$img_line     = $GLOBALS['phpgw']->common->image('phpgwapi','tree_vertline.gif');
			$img_split    = $GLOBALS['phpgw']->common->image('phpgwapi','tree_split.gif');
			$img_end      = $GLOBALS['phpgw']->common->image('phpgwapi','tree_end.gif');
			$img_leaf     = $GLOBALS['phpgw']->common->image('phpgwapi','tree_leaf.gif');
			$img_spc      = $GLOBALS['phpgw']->common->image('phpgwapi','tree_space.gif');

			/*********************************************/
			/*  Read text file with tree structure       */
			/*********************************************/

			/*********************************************/
			/* read file to $tree array                  */
			/* tree[x][0] -> tree level                  */
			/* tree[x][1] -> item text                   */
			/* tree[x][2] -> item link                   */
			/* tree[x][3] -> link target                 */
			/* tree[x][4] -> last item in subtree        */
			/* tree[x][5] -> if item 2 is meant to be    */
			/*               displayed, please list them */
			/*               here.                       */
			/*********************************************/

			$maxlevel=0;
			$cnt=-1;

			if($this->read_from_file)
			{
				$fd = fopen($treefile, 'r');
				if($fd==0)
				{
					die("menutree.inc : Unable to open file ".$treefile);
				}
				settype($treefile,'array');
				$treefile = array();
				while($buffer = fgets($fd, 4096))
				{
					$treefile[] = buffer;
				}
				fclose($fd);
			}
			if(is_array($treefile))
			{
				$ta = $treefile;
			}
			elseif(gettype($treefile) == 'string')
			{
				$ta = explode("\n",$treefile);
			}
			reset($ta);
			while (list($null,$buffer) = each($ta))
			{
				$cnt++;
				$tree[$cnt][0]=strspn($buffer,".");
				$tmp=rtrim(substr($buffer,$tree[$cnt][0]));
				$node=explode('|',$tmp); 
				$tree[$cnt][1]=chop($node[0]);
				$tree[$cnt][2]=chop($node[1]);
				if(count($node)>2)
				{
					$tree[$cnt][3]=chop($node[2]);
				}
				$tree[$cnt][4]=0;
				if(count($node) == 5)
				{
					$tree[$cnt][5]=$node[4];
				}
				if($tree[$cnt][0] > $maxlevel)
				{
					$maxlevel=$tree[$cnt][0];
				}
			}
			$c_tree = count($tree);
			for($i=0; $i<$c_tree; $i++)
			{
	//			if($i!=0)
	//			{
					$expand[$i]=0;
	//			}
	//			else
	//			{
	//				$expand[$i]=1;
	//			}
				if($tree[$i][0] == 1)
				{
					$visible[$i]=1;
				}
				else
				{
					$visible[$i]=0;
				}
				$levels[$i]=0;
			}

			/*********************************************/
			/*  Get Node numbers to expand               */
			/*********************************************/

			if($expandlevels!='')
			{
				$explevels = explode('|',$expandlevels);
				$c_exp = count($explevels);
				for($i=0;$i<$c_exp;$i++)
				{
					$expand[$explevels[$i]]=1;
				}
			}
			else
			{
				$c_exp = 0;
			}

			/*********************************************/
			/*  Find last nodes of subtrees              */
			/*********************************************/

			$lastlevel=$maxlevel;
			for ($i=$c_tree - 1; $i>=1; $i -= 1)
			{
				if($tree[$i][0] < $tree[$i + 1][0])
				{
					for($j=$tree[$i][0] + 1; $j <= $maxlevel; $j++)
					{
						$levels[$j]=0;
					}
				}
				if($levels[$tree[$i][0]]==0)
				{
					$levels[$tree[$i][0]]=1;
					$tree[$i][4]=1;
				}
				else
				{
					$tree[$i][4]=0;
				}
	//			$lastlevel=$tree[$i][0];  
			}

		/*********************************************/
		/*  Determine visible nodes                  */
		/*********************************************/
	//    $visible[0]=1;   // root is always visible
	//    $visible[1]=1;   // root is always visible
	//    $visible[2]=1;   // root is always visible
	//    $visible[3]=1;   // root is always visible
	//    $visible[4]=1;   // root is always visible
	//    $visible[5]=1;   // root is always visible
	//    $visible[6]=1;   // root is always visible
	//    $visible[7]=1;   // root is always visible
	//    $visible[8]=1;   // root is always visible
	//    $visible[9]=1;   // root is always visible
	//    $visible[10]=1;   // root is always visible
	//    $visible[11]=1;   // root is always visible
	//    $visible[12]=1;   // root is always visible
	//    $visible[13]=1;   // root is always visible
	//    $visible[14]=1;   // root is always visible
	//    $visible[15]=1;   // root is always visible
	//    $visible[16]=1;   // root is always visible
	//    $visible[17]=1;   // root is always visible
	//    $visible[18]=1;   // root is always visible
	//    $visible[19]=1;   // root is always visible
	//    $visible[20]=1;   // root is always visible
	//    $visible[21]=1;   // root is always visible
	//    $visible[22]=1;   // root is always visible
	//    $visible[23]=1;   // root is always visible
	//    $visible[24]=1;   // root is always visible
	//    $visible[25]=1;   // root is always visible
	//    $visible[26]=1;   // root is always visible
	//    $visible[27]=1;   // root is always visible
	//    $visible[28]=1;   // root is always visible

			for ($i=0; $i<$c_exp; $i++)
			{
				$n=$explevels[$i];
				if(($visible[$n]==1) && ($expand[$n]==1))
				{
					for($j=$n+1;$tree[$j][0]>$tree[$n][0];$j++)
					{
						if($tree[$j][0]==$tree[$n][0]+1)
						{
							$visible[$j]=1;
						}
					}
				}
			}

		/*********************************************/
		/*  Output nicely formatted tree             */
		/*********************************************/
	    
	//		for($i=0; $i<$maxlevel; $i++)
	//		{
	//			$levels[$i]=1;
	//		}
	  
			$maxlevel++;
	    
	//		$cnt=0;
	
			$browser = CreateObject('phpgwapi.browser');
			$browser->browser();
			$browser_agent = $browser->get_agent();
			switch($browser_agent)
			{
				case 'MOZILLA':
				case 'NETSCAPE':
					$text_size = 3;
					break;
				case 'IE':
					$text_size = 2;
					break;
			}

			$str = '';

			for($cnt=0;$cnt<$c_tree - 1;$cnt++)
			{
				if(!$visible[$cnt])
				{
					continue;
				}
				
				/****************************************/
				/* Create expand/collapse parameters    */
				/****************************************/
				$params='p=';
				for($i=0;$i<=$c_tree;$i++)
				{
					if(($expand[$i]==1) && ($cnt!=$i) || (($expand[$i]==0 && $cnt==$i) && ($tree[$cnt+1][0]>$tree[$cnt][0])))
					{
						if($params != 'p=')
						{
							$params .= '|';
						}
						$params .= $i;
					}
				}
				if($params=='p=')
				{
					$params='';
				}
	
	//			if($params != '')
	//			{
	//				$params = '&'.$params;
	//			}
	
	        /****************************************/
	        /* Always display the extreme top level */
	        /****************************************/
				if($cnt==0)
				{
	//				$str = '<table cellspacing="0" cellpadding="0" border="0" cols="'.($maxlevel+3).'" width="'.($maxlevel*16+100).'">'."\n";
					$str = '<table cellspacing="0" cellpadding="0" border="0" cols="'.($maxlevel+3).'" width="'.($maxlevel * 16 + $this->last_column_size).'">'."\n";
					$str .= '<a href="' . $GLOBALS['phpgw']->link('/'.$GLOBALS['phpgw_info']['flags']['currentapp'].'/index.php',$params) . '" target="_parent">' . $this->root_level_value . '</a>';
					$str .= "\n".'<tr>';
					for ($k=0; $k<$maxlevel; $k++)
					{
						$str .= '<td width=16></td>';
					}
					$str .= '<td width='.$this->last_column_size.'></td></tr>'."\n";
				}

				/****************************************/
				/* start new row                        */
				/****************************************/      
				$str .= '<tr>';

				/****************************************/
				/* vertical lines from higher levels    */
				/****************************************/
				$i=0;
				while ($i<$tree[$cnt][0]-1)
				{
					if ($levels[$i]==1)
					{
						$str .= '<td><img src="' . $img_line . '" alt="|"></td>';
					}
					else
					{
						$str .= '<td><img src="' . $img_spc . '" alt=" "></td>';
					}
					$i++;
				}

				/****************************************/
				/* corner at end of subtree or t-split  */
				/****************************************/         
				if ($tree[$cnt][4]==1)
				{
					$str .= '<td><img src="' . $img_end . '" alt="\"></td>';
					$levels[$tree[$cnt][0]-1]=0;
				}
				else
				{
					$str .= '<td><img src="' . $img_split . '" alt="|-"></td>';
					$levels[$tree[$cnt][0]-1]=1;    
				} 

				/********************************************/
				/* Node (with subtree) or Leaf (no subtree) */
				/********************************************/
				if($tree[$cnt+1][0]>$tree[$cnt][0])
				{
					$src = $REQUEST_URI;
					if(ereg('[\?\&]p=',$src) != 0)
					{
						$src = ereg_replace('[\?\&]p=([0-9\|])+','',$REQUEST_URI);
					}
	//				echo 'Src = '.$src."<br>\n";
					if(strpos(' '.$src,'?'))
					{
						$extra_param = '&';
					}
					else
					{
						$extra_param = '?';
					}
					if($expand[$cnt]==0)
					{
	//					$str .= '<td><a href="'.$GLOBALS['phpgw']->link('/'.$GLOBALS['phpgw_info']['flags']['currentapp'].'/'.basename($SCRIPT_FILENAME),$params).'"><img src="'.$img_expand.'" border="no" alt="+"></a></td>';
						$str .= '<td><a href="'.$src.$extra_param.$params.'"><img src="'.$img_expand.'" border="no" alt="+"></a></td>';
					}
					else
					{
	//					$str .= '<td><a href="'.$GLOBALS['phpgw']->link('/'.$GLOBALS['phpgw_info']['flags']['currentapp'].'/'.basename($SCRIPT_FILENAME),$params).'"><img src="'.$img_collapse.'" border="no" alt="-"></a></td>';
						$str .= '<td><a href="'.$src.$extra_param.$params.'"><img src="'.$img_collapse.'" border="no" alt="-"></a></td>';
					}
				}
				elseif(isset($tree[$cnt+1][0]))
				{
					/*************************/
					/* Tree Leaf             */
					/*************************/
					$str .= '<td><img src="' . $img_leaf . '" alt="o"></td>';         
				}

				/****************************************/
				/* output item text                     */
				/****************************************/
				$str .= '<td colspan="'.($maxlevel-$tree[$cnt][0]).'"><font face="'.$GLOBALS['phpgw_info']['theme']['font'].'" size="'.$text_size.'">';
				if ($tree[$cnt][5]=='')
				{
					if ($tree[$cnt][2]=='')
					{
						$str .= $tree[$cnt][1];
					}
					else
					{
						if(strpos(' '.$tree[$cnt][2],'?'))
						{
							$extra_param = '&';
						}
						else
						{
							$extra_param = '?';
						}
						$str .= '<a href="'.$tree[$cnt][2].$extra_param.$params.'" target="'.$tree[$cnt][3].'">'.$tree[$cnt][1].'</a>';
					}
				}
				else
				{
					$str .= $tree[$cnt][5];
				}
				$str .= '</font></td>';

				/****************************************/
				/* end row                              */
				/****************************************/

				$str .= '</tr>'."\n";
			}
			$str .= '</table>'."\n";

			return $str;

			/***************************************************/
			/* Tree file format                                */
			/*                                                 */
			/*                                                 */
			/* The first line is always of format :            */
			/* .[rootname]                                     */
			/*                                                 */
			/* each line contains one item, the line starts    */ 
			/* with a series of dots(.). Each dot is one level */
			/* deeper. Only one level at a time once is allowed*/
			/* Next comes the come the item name, link and     */
			/* link target, seperated by a |.                  */
			/*                                                 */  
			/* example:                                        */
			/*                                                 */  
			/* .top                                            */
			/* ..category 1                                    */
			/* ...item 1.1|item11.htm|main                     */
			/* ...item 2.2|item12.htm|main                     */
			/* ..category 2|cat2overview.htm|main              */
			/* ...item 2.1|item21.htm|main                     */
			/* ...item 2.2|item22.htm|main                     */
			/* ...item 2.3|item23.htm|main                     */
			/*                                                 */  
			/***************************************************/
		}
	}
