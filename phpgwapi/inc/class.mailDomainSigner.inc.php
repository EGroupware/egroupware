<?php
/**
 * Copyright 2011 Ahmad Amarullah
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */


/**
 * mailDomainSigner - PHP class for Add DKIM-Signature and DomainKey-Signature on your mail
 *
 * Requirement: >= PHP 5.3.0
 *              For lower version, find "#^--for ver < PHP5.3" comment
 *
 * @link http://code.google.com/p/php-mail-domain-signer/
 *
 * @package mailDomainSigner
 * @author Ahmad Amarullah
 */
class mailDomainSigner{

  ///////////////////////
  // PRIVATE VARIABLES //
  ///////////////////////
  private $pkid=null;
  private $s;
  private $d;

  //////////////////////
  // AGENT PROPERTIES //
  //////////////////////
  private $__app_name         = "PHP mailDomainSigner";
  private $__app_ver          = "0.1-20110129";
  private $__app_url          = "http://code.google.com/p/php-mail-domain-signer/";

  /**
   * Constructor
   * @param string $private_key Raw Private Key to Sign the mail
   * @param string $d The domain name of the signing domain
   * @param string $s The selector used to form the query for the public key
   * @author Ahmad Amarullah
   */
  public function __construct($private_key,$d,$s){
    // Get a private key
    $this->pkid = openssl_pkey_get_private($private_key);

    // Save Domain and Selector
    $this->d    = $d;
    $this->s    = $s;
  }

  ///////////////////////
  // PRIVATE FUNCTIONS //
  ///////////////////////

  /**
   * The nofws ("No Folding Whitespace") Canonicalization Algorithm
   * Function implementation according to RFC4870
   *
   * @link http://tools.ietf.org/html/rfc4870#page-19
   * @param array $raw_headers Array of Mail Headers
   * @param string $raw_body Raw Mail body
   * @return string nofws Canonicalizated data
   * @access public
   * @author Ahmad Amarullah
   */
  public function nofws($raw_headers,$raw_body){
    // nofws-ed headers
    $headers = array();

    // Loop the raw_headers
    foreach ($raw_headers as $header){
      // Replace all Folding Whitespace
      $headers[] = preg_replace('/[\r\t\n ]++/','',$header);
    }

    // Join headers with LF then Add it into data
    $data = implode("\n",$headers)."\n";

    // Loop Body Lines
    foreach(explode("\n","\n".str_replace("\r","",$raw_body)) as $line)
    {
      // Replace all Folding Whitespace from current line
      // then Add it into data
      $data .= preg_replace('/[\t\n ]++/','',$line)."\n";
    }

    // Remove Trailing empty lines then split it with LF
    $data = explode("\n",rtrim($data,"\n"));

    // Join array of data with CRLF and Append CRLF
    // to the resulting line
    $data = implode("\r\n",$data)."\r\n";

    // Return Canonicalizated Data
    return $data;
  }

  /**
   * The "relaxed" Header Canonicalization Algorithm
   * Function implementation according to RFC4871
   *
   * Originally taken from RelaxedHeaderCanonicalization
   * function in PHP-DKIM by Eric Vyncke
   *
   * @link http://tools.ietf.org/html/rfc4871#page-14
   * @link http://php-dkim.sourceforge.net/
   *
   * @param string $s Header String to Canonicalization
   * @return string Relaxed Header Canonicalizated data
   * @access public
   * @author Eric Vyncke
   */
  public function headRelaxCanon($s) {
    // Replace CR,LF and spaces into single SP
    $s=preg_replace("/\r\n\s+/"," ",$s) ;

    // Explode Header Line
    $lines=explode("\r\n",$s) ;

    // Loop the lines
    foreach ($lines as $key=>$line) {
      // Split the key and value
      list($heading,$value)=explode(":",$line,2) ;

      // Lowercase heading key
      $heading=strtolower($heading);

      // Compress useless spaces
      $value=preg_replace("/\s+/"," ",$value);

      // Don't forget to remove WSP around the value
      $lines[$key]=$heading.":".trim($value);
    }

    // Implode it again
    $s=implode("\r\n",$lines);

    // Return Canonicalizated Headers
    return $s;
  }

  /**
   * The "relaxed" Body Canonicalization Algorithm
   * Function implementation according to RFC4871
   *
   * @link http://tools.ietf.org/html/rfc4871#page-15
   *
   * @param string $body Body String to Canonicalization
   * @return string Relaxed Body Canonicalizated data
   * @access public
   * @author Ahmad Amarullah
   */
  public function bodyRelaxCanon($body) {
    // Return CRLF for empty body
    if ($body == ''){
      return "\r\n";
    }

    // Replace all CRLF to LF
    $body = str_replace("\r\n","\n",$body);

    // Replace LF to CRLF
    $body = str_replace("\n","\r\n",$body);

    // Ignores all whitespace at the end of lines
    $body=rtrim($body,"\r\n");

    // Canonicalizated String Variable
    $canon_body = '';

    // Split the body into lines
    foreach(explode("\r\n",$body) as $line){
      // Reduces all sequences of White Space within a line
      // to a single SP character
      $canon_body.= rtrim(preg_replace('/[\t\n ]++/',' ',$line))."\r\n";
    }

    // Return the Canonicalizated Body
    return $canon_body;
  }


  //////////////////////
  // PUBLIC FUNCTIONS //
  //////////////////////

  /**
   * DKIM-Signature Header Creator Function
   * implementation according to RFC4871
   *
   * Originally code inspired by AddDKIM
   * function in PHP-DKIM by Eric Vyncke
   * And rewrite it for better result
   *
   * The function use relaxed/relaxed canonicalization alghoritm
   * for better verifing validation
   *
   * different from original PHP-DKIM that used relaxed/simple
   * canonicalization alghoritm
   *
   * Doesn't include z, i and q tag for smaller data because
   * it doesn't really needed
   *
   * @link http://tools.ietf.org/html/rfc4871
   * @link http://php-dkim.sourceforge.net/
   *
   * @param string $h Signed header fields, A colon-separated list of header field names that identify the header fields presented to the signing algorithm
   * @param array $_h Array of headers in same order with $h (Signed header fields)
   * @param string $body Raw Email Body String
   * @return string DKIM-Signature Header String
   * @access public
   * @author Ahmad Amarullah
   */
  public function getDKIM($h,$_h,$body) {

    // Relax Canonicalization for Body
    $_b = $this->bodyRelaxCanon($body);

    // Canonicalizated Body Length [tag:l]
    $_l = strlen($_b);

    // Signature Timestamp [tag:t]
    $_t = time();

    // Hash of the canonicalized body [tag:bh]
    $_bh= base64_encode(sha1($_b,true));
    #^--for ver < PHP5.3 # $_bh= base64_encode(pack("H*",sha1($_b)));

    // Creating DKIM-Signature
    $_dkim = "DKIM-Signature: ".
                "v=1; ".                  // DKIM Version
                "a=rsa-sha1; ".           // The algorithm used to generate the signature "rsa-sha1"
                "s={$this->s}; ".         // The selector subdividing the namespace for the "d=" (domain) tag
                "d={$this->d}; ".         // The domain of the signing entity
                "l={$_l}; ".              // Canonicalizated Body length count
                "t={$_t}; ".              // Signature Timestamp
                "c=relaxed/relaxed; ".    // Message (Headers/Body) Canonicalization "relaxed/relaxed"
                "h={$h}; ".               // Signed header fields
                "bh={$_bh};\r\n\t".       // The hash of the canonicalized body part of the message
                "b=";                     // The signature data (Empty because we will calculate it later)

    // Wrap DKIM Header
    $_dkim = wordwrap($_dkim,76,"\r\n\t");

    // Canonicalization Header Data
    $_unsigned  = $this->headRelaxCanon(implode("\r\n",$_h)."\r\n{$_dkim}");

    // Sign Canonicalization Header Data with Private Key
    openssl_sign($_unsigned, $_signed, $this->pkid, OPENSSL_ALGO_SHA1);

    // Base64 encoded signed data
    // Chunk Split it
    // Then Append it $_dkim
    $_dkim   .= chunk_split(base64_encode($_signed),76,"\r\n\t");

    // Return trimmed $_dkim
    return trim($_dkim);
  }

  /**
   * DomainKey-Signature Header Creator Function
   * implementation according to RFC4870
   *
   * The function use nofws canonicalization alghoritm
   * for better verifing validation
   *
   * NOTE: the $h and $_h arguments must be in right order
   *       if to header location upper the from header
   *       it should ordered like "to:from", don't randomize
   *       the order for better validating result.
   *
   * NOTE: if your DNS TXT contained g=*, remove it
   *
   * @link http://tools.ietf.org/html/rfc4870
   *
   * @param string $h Signed header fields, A colon-separated list of header field names that identify the header fields presented to the signing algorithm
   * @param array $_h Array of headers in same order with $h (Signed header fields)
   * @param string $body Raw Email Body String
   * @return string DomainKey-Signature Header String
   * @access public
   * @author Ahmad Amarullah
   */
  public function getDomainKey($h,$_h,$body){
    // If $h = empty, dont add h tag into DomainKey-Signature
    $hval = '';
    if ($h)
      $hval= "h={$h}; ";

    // Creating DomainKey-Signature
    $_dk = "DomainKey-Signature: ".
              "a=rsa-sha1; ".           // The algorithm used to generate the signature "rsa-sha1"
              "c=nofws; ".              // Canonicalization Alghoritm "nofws"
              "d={$this->d}; ".         // The domain of the signing entity
              "s={$this->s}; ".         // The selector subdividing the namespace for the "d=" (domain) tag
              "{$hval}";                // If Exists - Signed header fields

    // nofws Canonicalization for headers and body data
    $_unsigned  = $this->nofws($_h,$body);

    // Sign nofws Canonicalizated Data with Private Key
    openssl_sign($_unsigned, $_signed, $this->pkid, OPENSSL_ALGO_SHA1);

    // Base64 encoded signed data
    // Chunk Split it
    $b = chunk_split(base64_encode($_signed),76,"\r\n\t");

    // Append sign data into b tag in $_dk
    $_dk.="b={$b}";

    // Return Wrapped and trimmed $_dk
    return trim(wordwrap($_dk,76,"\r\n\t"));
  }

  /**
   * Auto Sign RAW Mail Data with DKIM-Signature
   * and DomailKey-Signature
   *
   * It Support auto positioning Signed header fields
   *
   * @param string $mail_data Raw Mail Data to be signed
   * @param string $suggested_h Suggested Signed Header Fields, separated by colon ":"
   *                                      Default: string("from:to:subject")
   * @param bool $create_dkim If true, it will generate DKIM-Signature for $mail_data
   *                                      Default: boolean(true)
   * @param bool $create_domainkey If true, it will generate DomailKey-Signature for $mail_data
   *                                      Default: boolean(true)
   * @param integer $out_sign_header_only If true or 1, it will only return signature headers as String
   *                                      If 2, it will only return signature headers as Array
   *                                      If false or 0, it will return signature headers with original mail data as String
   *                                      Default: boolean(false)
   * @return mixed Signature Headers with/without original data as String/Array depended on $out_sign_header_only parameter
   * @access public
   * @author Ahmad Amarullah
   */
  public function sign(
      $mail_data,                                 // Raw Mail Data
      $suggested_h = "from:to:subject",           // Suggested Signed Header Fields
      $create_dkim = true,                        // Create DKIM-Signature Header
      $create_domainkey = true,                   // Create DomainKey-Signature Header
      $out_sign_header_only = false               // Return Signature Header Only without original data
    ){

    if (!$suggested_h) $suggested_h = "from:to:subject"; // Default Suggested Signed Header Fields

    // Remove all space and Lowercase Suggested Signed header fields then split it into array
    $_h = explode(":",strtolower(preg_replace('/[\r\t\n ]++/','',$suggested_h)));

    // Split Raw Mail data into $raw_headers and $body
    list($raw_headers, $body) = explode("\r\n\r\n",$mail_data,2);

    // Explode $raw_header into $header_list
    $header_list = preg_split("/\r\n(?![\t ])/", $raw_headers);

    // Empty Header Array
    $headers = array();

    // Loop $header_list
    foreach($header_list as $header){
      // Find Header Key for Array Key
      list($key) = explode(':',$header, 2);

      // Trim and Lowercase It
      $key = strtolower(trim($key));

      // If header with current key was exists
      // Change it into array
      if (isset($headers[$key])){
        // If header not yet array set as Array
        if (!is_array($headers[$key]))
          $headers[$key] = array($headers[$key]);

        // Add Current Header as next element
        $headers[$key][] = $header;
      }

      // If header with current key not exists
      // Insert header as string
      else{
        $headers[$key] = $header;
      }
    }

    // Now, lets find accepted Suggested Signed header fields
    // and reorder it to match headers position

    $accepted_h = array();          // For Accepted Signed header fields
    $accepted_headers = array();    // For Accepted Header

    // Loop the Headers Array
    foreach ($headers as $key=>$val){
      // Check if $val wasn't array
      // We don't want to include multiple headers as Signed header fields
      if (!is_array($val)){
        // Check if this header exists in Suggested Signed header fields
        if (in_array($key,$_h)){
          // If Exists, add it into accepted headers and accepted header fields
          $accepted_h[] = $key;
          $accepted_headers[] = $val;
        }
      }
    }

    // If it doesn't contain any $accepted_h
    // return false, because we don't have enough data
    // for signing email
    if (count($accepted_h)==0)
      return false;

    // Create $_hdata for Signed header fields
    // by imploding it with colon
    $_hdata = implode(":",$accepted_h);

    // New Headers Variable
    $_nh = array("x-domain-signer"=>"X-Domain-Signer: {$this->__app_name} {$this->__app_ver} <$this->__app_url>");

    // Create DKIM First
    if ($create_dkim)
      $_nh['dkim-signature'] = $this->getDKIM($_hdata,$accepted_headers,$body);

    // Now Create Domain-Signature
    if ($create_domainkey)
      $_nh['domainKey-signature'] = $this->getDomainKey($_hdata,$accepted_headers,$body);

    // Implode $_nh with \r\n
    $to_be_appended_headers = implode("\r\n",$_nh);

    // Return Immediately if
    // * $out_sign_header_only=true (as headers string)
    // * $out_sign_header_only=2    (as headers array)
    if ($out_sign_header_only===2)
      return $_nh;
    elseif ($out_sign_header_only)
      return "{$to_be_appended_headers}\r\n";

    // Return signed headers with original data
    return "{$to_be_appended_headers}\r\n{$mail_data}";
  }
}

?>