<?php
	/*******************************************************************\
	* phpGroupWare - GD Graph                                           *
	* http://www.phpgroupware.org                                       *
	* This program is part of the GNU project, see http://www.gnu.org/	*
	*                                                                   *
	* Written by Bettina Gille [ceb@phpgroupware.org]                   *
	*                                                                   *
	* Creates graphical statistics using GD graphics library            *
	* Copyright (C) 2003 Free Software Foundation, Inc                  *
	* ----------------------------------------------------------------- *
	* This class based on boGraph.php3                                  *
	* Double Choco Latte - Source Configuration Management System       *
	* Copyright (C) 1999  Michael L. Dean & Tim R. Norman               *
	* ----------------------------------------------------------------- *
	* This library is part of the phpGroupWare API                      *
	* ----------------------------------------------------------------- *
	* This library is free software; you can redistribute it and/or     *
	* modify it under the terms of the GNU General Public License as    *
	* published by the Free Software Foundation; either version 2 of    *
	* the License, or (at your option) any later version.               *
	*                                                                   *
	* This program is distributed in the hope that it will be useful,   *
	* but WITHOUT ANY WARRANTY; without even the implied warranty of    *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU  *
	* General Public License for more details.                          *
	*                                                                   *
	* You should have received a copy of the GNU General Public License *
	* along with this program; if not, write to the Free Software       *
	* Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.         *
	\*******************************************************************/
	/* $Id$ */

	class gdgraph
	{
		var $debug;
		var $title;
		var $caption_x;
		var $caption_y;
		var $lines_x;
		var $lines_y;
		var $line_captions_x;
		var $data;
		var $colors;
		var $color_legend;
		var $graph_width;
		var $graph_height;
		var $margin_top;
		var $margin_left;
		var $margin_bottom;
		var $margin_right;
		var $img;
	
		function gdgraph($debug = False)
		{
			$this->debug			= $debug;

			$this->title			= 'Gantt Chart';

			$this->caption_x		= 'x';
			$this->caption_y		= 'y';

			$this->num_lines_x 		= 30;
			$this->num_lines_y 		= 10;

			$this->line_captions_x	= array();
			$this->line_captions_y	= array();

			$this->data				= array();

			$this->colors			= array('red','green','blue','bright red','bright green','bright blue','dark red','dark green','dark blue');
			$this->color_legend		= array();
			$this->color_extra		= 'yellow';

			$this->graph_width		= 800;
			$this->graph_height		= 400;

			$this->margin_top		= 20;
			$this->margin_left		= 80;
			$this->margin_bottom	= 40;
			$this->margin_right		= 20;

			$this->img				= CreateObject('phpgwapi.gdimage');
			$this->temp_file		= $this->img->temp_file;
		}
	
		function rRender()
		{
			// Initialize image - map white since it's our background
			$this->img->width = $this->graph_width;
			$this->img->height = $this->graph_height;
			$this->img->Init();
			$this->img->SetColor(255, 255, 0);
			$this->img->ToBrowser();
			$this->img->Done();
		}

		function Render()
		{
			// Initialize image - map white since it's our background
			$this->img->width = $this->graph_width;
			$this->img->height = $this->graph_height;
			$this->img->Init();
			$this->img->SetColor(255, 255, 255);

			// Draw the captions
			$this->img->SetFont(2);
			$this->img->SetColor(0, 0, 0);
			$this->img->MoveTo($this->graph_width / 2, 2);
			$this->img->DrawText(array('text' => $this->title));
			//$this->img->MoveTo(2, $this->graph_height / 2);
			//$this->img->DrawText($this->caption_y, 'up', 'center');
			//$this->img->MoveTo($this->graph_width / 2, $this->graph_height - $this->img->GetFontHeight() - 2);
			//$this->img->DrawText($this->caption_x, '', 'center');

			// Draw the two axis
			$this->img->Line($this->margin_left, $this->margin_top, $this->margin_left, $this->graph_height - $this->margin_bottom + 4);
			$this->img->Line($this->margin_left - 4, $this->graph_height - $this->margin_bottom, $this->graph_width - $this->margin_right, $this->graph_height - $this->margin_bottom);

			// Draw dashed lines for x axis
			$linespace = ($this->graph_width - $this->margin_left - $this->margin_right) / ($this->num_lines_x - 1);
			for ($i = 1; $i < $this->num_lines_x; $i++)
			{
				$x = $i * $linespace + $this->margin_left;
				$this->img->SetColor(0, 0, 0);
				$this->img->Line($x, $this->graph_height - $this->margin_bottom - 4, $x, $this->graph_height - $this->margin_bottom + 4);
				$this->img->SetColor(200, 200, 200);
				$this->img->Line($x, $this->margin_top, $x, $this->graph_height - $this->margin_bottom - 4, 'dashed');
			}

			// Draw dashed lines for y axis
			$linespace = ($this->graph_height - $this->margin_top - $this->margin_bottom) / ($this->num_lines_y - 1);
			for ($i = 1; $i < $this->num_lines_y; $i++)
			{
				$y = $this->graph_height - $this->margin_bottom - ($i * $linespace);
				$this->img->SetColor(0, 0, 0);
				$this->img->Line($this->margin_left - 4, $y, $this->margin_left + 4, $y);
				$this->img->SetColor(200, 200, 200);
				$this->img->Line($this->margin_left + 4, $y, $this->graph_width - $this->margin_right, $y, 'dashed');
			}

			/* Find the largest numeric value in data (an array of arrays representing data)
			$largest = 0;
			reset($this->data);
			while (list($junk, $line) = each($this->data))
			{
				reset($line);
				while (list($junk2, $value) = each($line))
				{
					if ($value > $largest)
					$largest = $value;
				}
			}

			while ($largest < ($this->num_lines_y - 1))
				$largest = ($this->num_lines_y - 1);

			$spread = ceil($largest / ($this->num_lines_y - 1));
			$largest = $spread * ($this->num_lines_y - 1);*/

			$largest = $this->num_lines_x;

			// Draw the x axis text
			$this->img->SetColor(0, 0, 0);
			$this->img->SetFont(1);
			$linespace = ($this->graph_width - $this->margin_left - $this->margin_right) / ($this->num_lines_x - 1);
			reset($this->line_captions_x);
			$i = 0;
			while (list(,$text) = each($this->line_captions_x))
			{
				$this->img->MoveTo($i * $linespace + $this->margin_left, $this->graph_height - $this->margin_bottom + 8);
				$this->img->DrawText(array('text' => $text['date_formatted']));
				$i++;
			}

			// Draw the y axis text
			$linespace = ($this->graph_height - $this->margin_top - $this->margin_bottom) / ($this->num_lines_y - 1);
			$space = 1;
			for ($i = 0;$i<count($this->data);$i++)
			{
				$y = $this->graph_height - $this->margin_bottom - ($space * $linespace);
				$this->img->MoveTo($this->margin_left - 6, $y);
				$this->img->DrawText(array('text' => $this->data[$i]['title'],'justification' => 'right','margin_left' => $this->margin_left));
				$space++;
			}

			// Draw the lines for the data

			$this->img->SetColor(255, 0, 0);
			reset($this->data);

			if($this->debug)
			{
				_debug_array($this->data);
			}

			$i = 1;
			while (is_array($this->data) && list(,$line) = each($this->data))
			{
				if($line['extracolor'])
				{
					$this->img->SetColorByName($line['extracolor']);
				}
				else
				{
					$this->img->SetColorByName($this->colors[$line['color']]);
				}

				$x1 = $x2 = $y1 = $y2 = 0;

				$linespace = ($this->graph_height - $this->margin_top - $this->margin_bottom) / ($this->num_lines_y - 1);
				$y1 = $y2 = $this->graph_height - $this->margin_bottom - ($i * $linespace);

				$linespace = ($this->graph_width - $this->margin_left - $this->margin_right) / ($this->num_lines_x - 1);

				if ($line['sdate'] <= $this->line_captions_x[0]['date'] && $line['edate'] > $this->line_captions_x[0]['date'])
				{
					if($this->debug)
					{
						echo 'PRO sdate <= x sdate | PRO edate > x sdate<br>';
					}
					$x1 = $this->margin_left;
				}
				elseif($line['sdate'] >= $this->line_captions_x[0]['date'] && $line['edate'] <= $this->line_captions_x[$largest]['date'])
				{
					if($this->debug)
					{
						echo 'PRO sdate >= date! pro_sdate = ' . $line['sdate'] . ', pro_edate = ' . $line['edate'] . '<br>';
						echo 'PRO sdate >= date! pro_sdate_formatted = ' . $GLOBALS['phpgw']->common->show_date($line['sdate'],$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']) . ', pro_edate_formatted = ' . $GLOBALS['phpgw']->common->show_date($line['edate'],$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']) . '<br>';
						echo 'x sdate: ' . $this->line_captions_x[0]['date'] . ', x edate: ' . $this->line_captions_x[$largest]['date'] . '<br><br>';
					}

					for($y=0;$y<$largest;$y++)
					{
						if($line['sdate'] == $this->line_captions_x[$y]['date'])
						{
							$x1 = $y * $linespace + $this->margin_left;
						}
					}
				}
				else
				{
					$x1 = $largest * $linespace + $this->margin_left;
				}

				if ($line['edate'] >= $this->line_captions_x[$largest]['date'])
				{
					if($this->debug)
					{
						echo 'PRO edate >= x edate! pro_edate = ' . $line['edate'] . '<br>';
						echo 'PRO edate >= x edate! pro_edate_formatted = ' . $GLOBALS['phpgw']->common->show_date($line['edate'],$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']) . '<br>';
						echo 'x edate: ' . $this->line_captions_x[$largest]['date'] . '<br>';
					}

					$x2 = $this->graph_width - $this->margin_right;
				}
				elseif($line['edate'] <= $this->line_captions_x[$largest]['date'] && $line['edate'] >= $this->line_captions_x[0]['date'])
				{
					for($y=0;$y<$largest;$y++)
					{
						if($line['edate'] == $this->line_captions_x[$y]['date'])
						{
							$x2 = $y * $linespace + $this->margin_left;
						}
					}
				}
				else
				{
					$x2 = $largest * $linespace + $this->margin_left;
				}

				for ($w = 0; $w < 7; $w++)
				{
					$this->img->Line(1+$x1,$y1+$w,$x2,$y2+$w);
				}
				$color_index++;
				$i++;
			}
			$this->img->ToBrowser();
			$this->img->Done();
		}

		function Open()
		{
		print('<script language="JavaScript">');
		print('window.open(\'main.php3?menuAction=boGraph.Show&');
		if (ereg('MSIE', $GLOBALS['HTTP_USER_AGENT']))
			print('DCLINFO=' . $GLOBALS['DCLINFO'] . '&');
		print($this->ToURL() . '\', \'graph\', \'width=' . ($this->graph_width + 20) . ',height=' . ($this->graph_height + 20) . ',resizable=yes,scrollbars=yes\');');
		print('</script>');
	}

	function Show()
	{
		$this->FromURL();
		$this->Render();
	}

	function FromURL()
	{
		$this->title = $GLOBALS['title'];
		$this->caption_x = $GLOBALS['caption_x'];
		$this->caption_y = $GLOBALS['caption_y'];
		$this->num_lines_x = $GLOBALS['num_lines_x'];
		$this->num_lines_y = $GLOBALS['num_lines_y'];
		$this->line_captions_x = explode(',', $GLOBALS['line_captions_x']);
		
		$dataURL = explode('~', $GLOBALS['data']);
		$this->data = array();
		while (list($junk, $line) = each($dataURL))
			$this->data[] = explode(',', $line);
		
		$this->colors = explode(',', $GLOBALS['colors']);
		$this->color_legend = explode(',', $GLOBALS['color_legend']);
		$this->graph_width = $GLOBALS['graph_width'];
		$this->graph_height = $GLOBALS['graph_height'];
		$this->margin_top = $GLOBALS['margin_top'];
		$this->margin_left = $GLOBALS['margin_left'];
		$this->margin_bottom = $GLOBALS['margin_bottom'];
		$this->margin_right = $GLOBALS['margin_right'];
	}
	
	function ToURL()
	{
		$url = 'title=' . rawurlencode($this->title) . '&';
		$url .= 'caption_x=' . rawurlencode($this->caption_x) . '&';
		$url .= 'caption_y=' . rawurlencode($this->caption_y) . '&';
		$url .= 'num_lines_x=' . $this->num_lines_x . '&';
		$url .= 'num_lines_y=' . $this->num_lines_y . '&';
		$url .= 'line_captions_x=' . rawurlencode(implode(',', $this->line_captions_x)) . '&';
		reset($this->data);
		$dataURL = '';
		while(list($junk, $line) = each($this->data))
		{
			if ($dataURL != '')
				$dataURL .= '~';
			$dataURL .= implode(',', $line);
		}
		$url .= 'data=' . $dataURL . '&';
		$url .= 'colors=' . implode(',', $this->colors) . '&';
		$url .= 'color_legend=' . rawurlencode(implode(',', $this->color_legend)) . '&';
		$url .= 'graph_width=' . $this->graph_width . '&';
		$url .= 'graph_height=' . $this->graph_height . '&';
		$url .= 'margin_top=' . $this->margin_top . '&';
		$url .= 'margin_left=' . $this->margin_left . '&';
		$url .= 'margin_bottom=' . $this->margin_bottom . '&';
		$url .= 'margin_right=' . $this->margin_right;

		return $url;
	}
	}
?>
