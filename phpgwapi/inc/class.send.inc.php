<?php
  /**************************************************************************\
  * phpGroupWare - smtp mailer                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Itzchak Rehberg <izzysoft@qumran.org>                         *
  * ------------------------------------------------                         *
  *  This module should replace php's mail() function. It is fully syntax    *
  *  compatible. In addition, when an error occures, a detailed error info   *
  *  is stored in the array $send->err (see ../inc/email/global.inc.php for  *
  *  details on this variable).                                              *
  \**************************************************************************/

  /* $Id$ */

class send {
    var $err    = array("code","msg","desc");
    var $to_res = array();

    function send() {
      $this->err["code"] = " ";
      $this->err["msg"]  = " ";
      $this->err["desc"] = " ";
    }

    function msg($service, $to, $subject, $body, $msgtype="", $cc="", $bcc="") {
      global $phpgw_info, $phpgw, $attach_sig;

      if ($service == "email") {
          $now = getdate();
          $header  = "Date: " . gmdate("D, d M Y H:i:s") . " +0000\n";
          $header .= "From: ".$phpgw_info["user"]["fullname"]." <".$phpgw_info["user"]["preferences"]["email"]["address"].">\n";
          $header .= "Reply-To: ".$phpgw_info["user"]["preferences"]["email"]["address"]."\n";
          $header .= "To: $to\n";
          if (!empty($cc)) {
            $header .= "Cc: $cc\n";
          }
          if (!empty($bcc)) {
            $header .= "Bcc: $bcc\n";
          }
          if (!empty($msgtype)) {
            $header .= "X-phpGW-Type: $msgtype\n";
          }
          $header .= "X-Mailer: phpGroupWare (http://www.phpgroupware.org)\n";

          if ($phpgw_info["user"]["preferences"]["email"]["email_sig"] && $attach_sig) {
             $body .= "\n-----\n" . $phpgw_info["user"]["preferences"]["email"]["email_sig"];
          }

          if (ereg("Message-Boundary", $body)) 
          {
            $header .= "Subject: " . stripslashes($subject) . "\n"
                    . "MIME-Version: 1.0\n"
                    . "Content-Type: multipart/mixed;\n"
                    . " boundary=\"Message-Boundary\"\n\n"
                    . "--Message-Boundary\n"
                    . "Content-type: text/plain; charset=US-ASCII\n";
//            if (!empty($msgtype)) {
//              $header .= "Content-type: text/plain; phpgw-type=".$msgtype."\n";
//            }

            $header .= "Content-Disposition: inline\n"
                    . "Content-transfer-encoding: 7BIT\n\n"
                    . $body;
            $body = "";
          } else {
            $header .= "Subject: " . stripslashes($subject) . "\n"
                    . "MIME-version: 1.0\n"
                    . "Content-type: text/plain; charset=\"".lang("charset")."\"\n";
            if (!empty($msgtype)) {
              $header .= "Content-type: text/plain; phpgw-type=".$msgtype."\n";
            }
            $header .= "Content-Disposition: inline\n"
                    . "Content-description: Mail message body\n";
          }
          if ($phpgw_info["user"]["preferences"]["email"]["mail_server_type"] == "imap" && $phpgw_info["user"]["apps"]["email"]){
            $stream = $phpgw->msg->login("Sent");
            $phpgw->msg->append($stream, "Sent", $header, $body);
            $phpgw->msg->close($stream);
          }
          if (strlen($cc)>1) $to .= ",".$cc;

          if (strlen($bcc)>1) $to .= ",".$bcc;

          $returnccode = $this->smail($to, "", $body, $header);

          return $returnccode;
      } elseif ($type == "nntp") {
      }
    }

 // ==================================================[ some sub-functions ]===

 function socket2msg($socket) {
   $followme = "-"; $this->err["msg"] = "";
   do {
     $rmsg = fgets($socket,255);
// echo "< $rmsg<BR>\n";
     $this->err["code"] = substr($rmsg,0,3);
     $followme = substr($rmsg,3,1);
     $this->err["msg"] = substr($rmsg,4);
     if (substr($this->err["code"],0,1) != 2 && substr($this->err["code"],0,1) != 3) {
       $rc  = fclose($socket);
       return false;
     }
     if ($followme = " ") { break; }
   } while ($followme = "-");
   return true;
 }

 function msg2socket($socket,$message) { // send single line\n
  // echo "raw> $message<BR>\n";
  // echo "hex> ".bin2hex($message)."<BR>\n";
  $rc = fputs($socket,"$message");
  if (!$rc) {
    $this->err["code"] = "420";
    $this->err["msg"]  = "lost connection";
    $this->err["desc"] = "Lost connection to smtp server.";
    $rc  = fclose($socket);
    return false;
  }
  return true;
 }

 function put2socket($socket,$message) { // check for multiple lines 1st
  $pos = strpos($message,"\n");
  if (!is_int($pos)) { // no new line found
    $message .= "\r\n";
    $this->msg2socket($socket,$message);
  } else {                         // multiple lines, we have to split it
    do {
      $msglen = $pos + 1;
      $msg = substr($message,0,$msglen);
      $message = substr($message,$msglen);
      $pos = strpos($msg,"\r\n");
      if (!is_int($pos)) { // line not terminated
        $msg = chop($msg)."\r\n";
      }
      $pos = strpos($msg,".");  // escape leading periods
      if (is_int($pos) && !$pos) {
        $msg = "." . $msg;
      }
      if (!$this->msg2socket($socket,$msg)) { return false; }
      $pos = strpos($message,"\n");
    } while (strlen($message)>0);
  }
  return true;
 }

 function check_header($subject,$header) { // check if header contains subject
                                           // and is correctly terminated
  $header = chop($header);
  $header .= "\n";
  if (is_string($subject) && !$subject) { // no subject specified
   return $header;
  }
  $theader = strtolower($header);
  $pos  = strpos($theader,"\nsubject:");
  if (is_int($pos)) { // found after a new line
   return $header;
  }
  $pos = strpos($theader,"subject:");
  if (is_int($pos) && !$pos) { // found at start
    return $header;
  }
  $pos = substr($subject,"\n");
  if (!is_int($pos)) $subject .= "\n";
  $subject = "Subject: " .$subject;
  $header .= $subject;
  return $header;
 }

 // ==============================================[ main function: smail() ]===

 function smail($to,$subject,$message,$header) {
  global $phpgw_info;

  $fromuser = $phpgw_info["user"]["preferences"]["email"]["address"];
  $mymachine = $phpgw_info["server"]["hostname"];
  $errcode = ""; $errmsg = ""; // error code and message of failed connection
  $timeout = 5;                // timeout in secs

  // now we try to open the socket and check, if any smtp server responds
  $socket = fsockopen($phpgw_info["server"]["smtp_server"],$phpgw_info["server"]["smtp_port"],$errcode,$errmsg,$timeout);
  if (!$socket) {
    $this->err["code"] = "420";
    $this->err["msg"]  = "$errcode:$errmsg";
    $this->err["desc"] = "Connection to ".$phpgw_info["server"]["smtp_server"].":".$phpgw_info["server"]["smtp_port"]." failed - could not open socket.";
    return false;
  } else {
    $rrc = $this->socket2msg($socket);
  }

  // now we can send our message. 1st we identify ourselves and the sender
  $cmds = array (
     "\$src = \$this->msg2socket(\$socket,\"HELO \$mymachine\r\n\");",
     "\$rrc = \$this->socket2msg(\$socket);",
     "\$src = \$this->msg2socket(\$socket,\"MAIL FROM:<\$fromuser>\r\n\");",
     "\$rrc = \$this->socket2msg(\$socket);"
  );
  for ($src=true,$rrc=true,$i=0; $i<count($cmds);$i++) {
   eval ($cmds[$i]);
   if (!$src || !$rrc) return false;
  }

  // now we've got to evaluate the $to's
  $toaddr = explode(",",$to);
  $numaddr = count($toaddr);
  for ($i=0; $i<$numaddr; $i++) {
    $src = $this->msg2socket($socket,"RCPT TO:<$toaddr[$i]>\r\n");
    $rrc = $this->socket2msg($socket);
    $this->to_res[$i][addr] = $toaddr[$i];     // for lateron validation
    $this->to_res[$i][code] = $this->err["code"];
    $this->to_res[$i][msg]  = $this->err["msg"];
    $this->to_res[$i][desc] = $this->err["desc"];
  }

  //now we have to make sure that at least one $to-address was accepted
  $stop = 1;
  for ($i=0;$i<count($this->to_res);$i++) {
    $rc = substr($this->to_res[$i][code],0,1);
    if ($rc == 2) { // at least to this address we can deliver
      $stop = 0;
    }
  }
  if ($stop) return false;  // no address found we can deliver to

  // now we can go to deliver the message!
  if (!$this->msg2socket($socket,"DATA\r\n")) return false;
  if (!$this->socket2msg($socket)) return false;
  if ($header != "") {
    $header = $this->check_header($subject,$header);
    if (!$this->put2socket($socket,$header)) return false;
    if (!$this->put2socket($socket,"\r\n")) return false;
  }
  $message  = chop($message);
  $message .= "\n";
  if (!$this->put2socket($socket,$message)) return false;
  if (!$this->msg2socket($socket,".\r\n")) return false;
  if (!$this->socket2msg($socket)) return false;
  if (!$this->msg2socket($socket,"QUIT\r\n")) return false;
  Do {
   $closing = $this->socket2msg($socket);
  } while ($closing);
  return true;
 }

// end of class
}
