<?php
	/**
			 **  date.php
			 **
			 **  Takes a date and parses it into a usable format.  The form that a
			 **  date SHOULD arrive in is:
			 **        <Tue,> 29 Jun 1999 09:52:11 -0500 (EDT)
			 **  (as specified in RFC 822) -- 'Tue' is optional
			 **
			 **  $Id$
			 **/

	class transformdate
	{

		// corrects a time stamp to be the local time
		function getGMTSeconds($stamp, $gmt) 
		{
			global $invert_time;

			if (($gmt == 'Pacific') || ($gmt == 'PST'))
				$gmt = '-0800';
			else if (($gmt == 'EDT'))
				$gmt = '-0400';
			else if (($gmt == 'Eastern') || ($gmt == 'EST') || ($gmt == 'CDT'))
				$gmt = '-0500';
			else if (($gmt == 'Central') || ($gmt == 'CST') || ($gmt == 'MDT'))
				$gmt = '-0600';
			else if (($gmt == 'Mountain') || ($gmt == 'MST') || ($gmt == 'PDT'))
				$gmt = '-0700';
			else if ($gmt == 'BST')
				$gmt = '+0100';
			else if ($gmt == 'EET')
				$gmt = '+0200';
			else if ($gmt == 'GMT')
				$gmt = '+0000';
			else if ($gmt == 'HKT')
				$gmt = '+0800';
			else if ($gmt == 'IST')
				$gmt = '+0200';
			else if ($gmt == 'JST')
				$gmt = '+0900';
			else if ($gmt == 'MET')
				$gmt = '+0100';
			else if ($gmt == 'MET DST' || $gmt == 'METDST')
				$gmt = '+0200';
				
			if (substr($gmt, 0, 1) == '-') 
			{
				$neg = true;
				$gmt = substr($gmt, 1, strlen($gmt));
			} 
			else if (substr($gmt, 0, 1) == '+') 
			{
				$neg = false;
				$gmt = substr($gmt, 1, strlen($gmt));
			} 
			else
				$neg = false;
			
			$gmt = substr($gmt, 0, 2);
			$gmt = $gmt * 3600;
			if ($neg == true)
				$gmt = "-$gmt";
			else
				$gmt = "+$gmt";
				
			/** now find what the server is at **/
			$current = date('Z', time());
			if ($invert_time)
				$current = - $current;
			$stamp = (int)$stamp - (int)$gmt + (int)$current;
			
			return $stamp;
		}
		
		function getLongDateString($stamp) 
		{
			return date('D, F j, Y g:i a', $stamp);
		}
		
		function getDateString($stamp) 
		{
		
			global $invert_time;
			
			$now = time();
			$dateZ = date('Z', $now);
			if ($invert_time)
				$dateZ = - $dateZ;
			$midnight = $now - ($now % 86400) - $dateZ;
			
			if ($midnight < $stamp) 
			{
				// Today
				return date('g:i a', $stamp);
			} 
			else if ($midnight - (60 * 60 * 24 * 6) < $stamp) 
			{
				// This week
				return date('D, g:i a', $stamp);
			} 
			else 
			{
				// before this week
				return date('M j, Y', $stamp);
			}
		}
		
		function getTimeStamp($dateParts) 
		{
			/** $dateParts[0] == <day of week>   Mon, Tue, Wed
			 ** $dateParts[1] == <day of month>  23
			 ** $dateParts[2] == <month>  Jan, Feb, Mar
			 ** $dateParts[3] == <year>	1999
			 ** $dateParts[4] == <time>      18:54:23 (HH:MM:SS)
			 ** $dateParts[5] == <from GMT>      +0100
			 ** $dateParts[6] == <zone>          (EDT)
			 **
			 ** NOTE:  In RFC 822, it states that <day of week> is optional.
			 **	   In that case, dateParts[0] would be the <day of month>
			 **        and everything would be bumped up one.
			 **/

			// Simply check to see if the first element in the dateParts
			// array is an integer or not.
			//    Since the day of week is optional, this check is needed.  
			//    
			//    The old code used eregi('mon|tue|wed|thu|fri|sat|sun',
			//    $dateParts[0], $tmp) to find if the first element was the
			//    day of week or day of month. This is an expensive call
			//    (processing time) to have inside a loop. Doing it this way
			//    saves quite a bit of time for large mailboxes.
			//
			//    It is also quicker to call explode only once rather than
			//    the 3 times it was getting called by calling the functions
			//    getHour, getMinute, and getSecond.
			//
			if (intval(trim($dateParts[0])) > 0) 
			{
				$string = $dateParts[0] . ' ' . $dateParts[1] . ' ' . 
									 $dateParts[2] . ' ' . $dateParts[3];
				return $this->getGMTSeconds(strtotime($string), $dateParts[4]);
			}
			$string = $dateParts[0] . ' ' . $dateParts[1] . ' ' .
								$dateParts[2] . ' ' . $dateParts[3] . ' ' . $dateParts[4];
			if (isset($dateParts[5]))
				return $this->getGMTSeconds(strtotime($string), $dateParts[5]);
			else
				return $this->getGMTSeconds(strtotime($string), '');
		}
		
		// I use this function for profiling. Should never be called in
		// actual versions of felamimail released to public.
		function getmicrotime() 
		{
			$mtime = microtime();
			$mtime = explode(' ',$mtime);
			$mtime = $mtime[1] + $mtime[0];
			return ($mtime);
		}
	}
?>
