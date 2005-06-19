<?php

require_once 'Horde/Util.php';

$GLOBALS['_HORDE_STRING_CHARSET'] = 'iso-8859-1';

/**
 * The String:: class provides static methods for charset and locale safe
 * string manipulation.
 *
 * $Horde: framework/Util/String.php,v 1.50 2005/02/10 17:09:44 jan Exp $
 *
 * Copyright 2003-2005 Jan Schneider <jan@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @since   Horde 3.0
 * @package Horde_Util
 */
class String {

    /**
     * Sets a default charset that the String:: methods will use if none is
     * explicitely specified.
     *
     * @param string $charset  The charset to use as the default one.
     */
    function setDefaultCharset($charset)
    {
        $GLOBALS['_HORDE_STRING_CHARSET'] = $charset;
        if (Util::extensionExists('mbstring') &&
            function_exists('mb_regex_encoding')) {
            @mb_regex_encoding($charset);
        }
    }

    /**
     * Converts a string from one charset to another.
     *
     * Works only if either the iconv or the mbstring extension
     * are present and best if both are available.
     * The original string is returned if conversion failed or none
     * of the extensions were available.
     *
     * @param mixed $input     The data to be converted. If $input is an an
     *                         array, the array's values get converted
     *                         recursively.
     * @param string $from     The string's current charset.
     * @param string $to       The charset to convert the string to. If not
     *                         specified, the global variable
     *                         $_HORDE_STRING_CHARSET will be used.
     * @param bool $recursion  Internally used.
     *
     * @return string  The converted string.
     */
    function convertCharset($input, $from, $to = null, $recursion = false)
    {
        /* Get the user's default character set if none passed in. */
        if (is_null($to)) {
            $to = $GLOBALS['_HORDE_STRING_CHARSET'];
        }

        /* If the from and to character sets are identical, return now. */
        if (!$recursion) {
            $from = String::lower($from);
            $to = String::lower($to);
        }
        if ($from == $to) {
            return $input;
        }

        if (is_array($input)) {
            $tmp = array();
            foreach ($input as $key => $val) {
                $tmp[String::convertCharset($key, $from, $to, true)] = String::convertCharset($val, $from, $to, true);
            }
            return $tmp;
        }
        if (is_object($input)) {
            $vars = get_object_vars($input);
            foreach ($vars as $key => $val) {
                $input->$key = String::convertCharset($val, $from, $to, true);
            }
            return $input;
        }

        if (!is_string($input)) {
            return $input;
        }

        $output = false;

        /* Use utf8_[en|de]code() if possible. */
        $from_check = (($from == 'iso-8859-1') || ($from == 'us-ascii'));
        if ($from_check && ($to == 'utf-8')) {
            return utf8_encode($input);
        }

        $to_check = (($to == 'iso-8859-1') || ($to == 'us-ascii'));
        if (($from == 'utf-8') && $to_check) {
            return utf8_decode($input);
        }

        /* First try iconv with transliteration. */
        if ($from != 'utf7-imap' &&
            $to != 'utf7-imap' &&
            Util::extensionExists('iconv')) {
            ini_set('track_errors', 1);
            /* We need to tack an extra character temporarily because
             * of a bug in iconv() if the last character is not a 7
             * bit ASCII character. */
            $output = @iconv($from, $to . '//TRANSLIT', $input . 'x');
            if (isset($php_errormsg)) {
                $output = false;
            } else {
                $output = String::substr($output, 0, -1, $to);
            }
            ini_restore('track_errors');
        }

        /* Next try mbstring. */
        if (!$output && Util::extensionExists('mbstring')) {
            $output = @mb_convert_encoding($input, $to, $from);
        }

        /* At last try imap_utf7_[en|de]code if appropriate. */
        if (!$output && Util::extensionExists('imap')) {
            if ($from_check && ($to == 'utf7-imap')) {
                return @imap_utf7_encode($input);
            }
            if (($from == 'utf7-imap') && $to_check) {
                return @imap_utf7_decode($input);
            }
        }

        return !$output ? $input : $output;
    }

    /**
     * Makes a string lowercase.
     *
     * @param string  $string   The string to be converted.
     * @param boolean $locale   If true the string will be converted based on a
     *                          given charset, locale independent else.
     * @param string  $charset  If $locale is true, the charset to use when
     *                          converting. If not provided the current charset.
     *
     * @return string  The string with lowercase characters
     */
    function lower($string, $locale = false, $charset = null)
    {
        static $lowers;

        if ($locale) {
            /* The existence of mb_strtolower() depends on the platform. */
            if (Util::extensionExists('mbstring') &&
                function_exists('mb_strtolower')) {
                if (is_null($charset)) {
                    $charset = $GLOBALS['_HORDE_STRING_CHARSET'];
                }
                $ret = @mb_strtolower($string, $charset);
                if (!empty($ret)) {
                    return $ret;
                }
            }
            return strtolower($string);
        }

        if (!isset($lowers)) {
            $lowers = array();
        }
        if (!isset($lowers[$string])) {
            $language = setlocale(LC_CTYPE, 0);
            setlocale(LC_CTYPE, 'en_US');
            $lowers[$string] = strtolower($string);
            setlocale(LC_CTYPE, $language);
        }

        return $lowers[$string];
    }

    /**
     * Makes a string uppercase.
     *
     * @param string  $string   The string to be converted.
     * @param boolean $locale   If true the string will be converted based on a
     *                          given charset, locale independent else.
     * @param string  $charset  If $locale is true, the charset to use when
     *                          converting. If not provided the current charset.
     *
     * @return string  The string with uppercase characters
     */
    function upper($string, $locale = false, $charset = null)
    {
        static $uppers;

        if ($locale) {
            /* The existence of mb_strtoupper() depends on the
             * platform. */
            if (function_exists('mb_strtoupper')) {
                if (is_null($charset)) {
                    $charset = $GLOBALS['_HORDE_STRING_CHARSET'];
                }
                $ret = @mb_strtoupper($string, $charset);
                if (!empty($ret)) {
                    return $ret;
                }
            }
            return strtoupper($string);
        }

        if (!isset($uppers)) {
            $uppers = array();
        }
        if (!isset($uppers[$string])) {
            $language = setlocale(LC_CTYPE, 0);
            setlocale(LC_CTYPE, 'en_US');
            $uppers[$string] = strtoupper($string);
            setlocale(LC_CTYPE, $language);
        }

        return $uppers[$string];
    }

    /**
     * Returns a string with the first letter capitalized if it is
     * alphabetic.
     *
     * @param string  $string   The string to be capitalized.
     * @param boolean $locale   If true the string will be converted based on a
     *                          given charset, locale independent else.
     * @param string  $charset  The charset to use, defaults to current charset.
     *
     * @return string  The capitalized string.
     */
    function ucfirst($string, $locale = false, $charset = null)
    {
        if ($locale) {
            $first = String::substr($string, 0, 1, $charset);
            if (String::isAlpha($first, $charset)) {
                $string = String::upper($first, true, $charset) . String::substr($string, 1, null, $charset);
            }
        } else {
            $string = String::upper(substr($string, 0, 1), false) . substr($string, 1);
        }
        return $string;
    }

    /**
     * Returns part of a string.
     *
     * @param string $string  The string to be converted.
     * @param int $start      The part's start position, zero based.
     * @param int $length     The part's length.
     * @param string $charset The charset to use when calculating the part's
     *                        position and length, defaults to current charset.
     *
     * @return string  The string's part.
     */
    function substr($string, $start, $length = null, $charset = null)
    {
        if (Util::extensionExists('mbstring')) {
            if (is_null($charset)) {
                $charset = $GLOBALS['_HORDE_STRING_CHARSET'];
            }
            if (is_null($length)) {
                $length = String::length($string, $charset);
            }
            $ret = @mb_substr($string, $start, $length, $charset);
            if (!empty($ret)) {
                return $ret;
            }
        }
        if (is_null($length)) {
            $length = String::length($string);
        }
        return substr($string, $start, $length);
    }

    /**
     * Returns the character (not byte) length of a string.
     *
     * @param string $string  The string to return the length of.
     * @param string $charset The charset to use when calculating the string's
     *                        length.
     *
     * @return string  The string's part.
     */
    function length($string, $charset = null)
    {
        if (Util::extensionExists('mbstring')) {
            if (is_null($charset)) {
                $charset = $GLOBALS['_HORDE_STRING_CHARSET'];
            }
            $ret = @mb_strlen($string, $charset);
            if (!empty($ret)) {
                return $ret;
            }
        }
        return strlen($string);
    }

    /**
     * Returns the numeric position of the first occurrence of $needle
     * in the $haystack string.
     *
     * @param string $haystack  The string to search through.
     * @param string $needle    The string to search for.
     * @param int $offset       Allows to specify which character in haystack
     *                          to start searching.
     * @param string $charset   The charset to use when searching for the
     *                          $needle string.
     *
     * @return int  The position of first occurrence.
     */
    function pos($haystack, $needle, $offset = 0, $charset = null)
    {
        if (Util::extensionExists('mbstring')) {
            if (is_null($charset)) {
                $charset = $GLOBALS['_HORDE_STRING_CHARSET'];
            }
            ini_set('track_errors', 1);
            $ret = @mb_strpos($haystack, $needle, $offset, $charset);
            ini_restore('track_errors');
            if (!isset($php_errormsg)) {
                return $ret;
            }
        }
        return strpos($haystack, $needle, $offset);
    }

    /**
     * Returns a string padded to a certain length with another string.
     *
     * This method behaves exactly like str_pad but is multibyte safe.
     *
     * @param string $input    The string to be padded.
     * @param int $length      The length of the resulting string.
     * @param string $pad      The string to pad the input string with. Must
     *                         be in the same charset like the input string.
     * @param const $type      The padding type. One of STR_PAD_LEFT,
     *                         STR_PAD_RIGHT, or STR_PAD_BOTH.
     * @param string $charset  The charset of the input and the padding
     *                         strings.
     *
     * @return string  The padded string.
     */
    function pad($input, $length, $pad = ' ', $type = STR_PAD_RIGHT,
                 $charset = null)
    {
        $mb_length = String::length($input, $charset);
        $sb_length = strlen($input);
        $pad_length = String::length($pad, $charset);

        /* Return if we already have the length. */
        if ($mb_length >= $length) {
            return $input;
        }

        /* Shortcut for single byte strings. */
        if ($mb_length == $sb_length && $pad_length == strlen($pad)) {
            return str_pad($input, $length, $pad, $type);
        }

        switch ($type) {
        case STR_PAD_LEFT:
            $left = $length - $mb_length;
            $output = String::substr(str_repeat($pad, ceil($left / $pad_length)), 0, $left, $charset) . $input;
            break;
        case STR_PAD_BOTH:
            $left = floor(($length - $mb_length) / 2);
            $right = ceil(($length - $mb_length) / 2);
            $output = String::substr(str_repeat($pad, ceil($left / $pad_length)), 0, $left, $charset) .
                $input .
                String::substr(str_repeat($pad, ceil($right / $pad_length)), 0, $right, $charset);
            break;
        case STR_PAD_RIGHT:
            $right = $length - $mb_length;
            $output = $input . String::substr(str_repeat($pad, ceil($right / $pad_length)), 0, $right, $charset);
            break;
        }

        return $output;
    }

    /**
     * Wraps the text of a message.
     *
     * @todo Make multibyte-save.
     *
     * @access public
     *
     * @param string $text                 String containing the text to wrap.
     * @param optional integer $length     Wrap $text at this number of
     *                                     characters.
     * @param optional string $break_char  Character(s) to use when breaking
     *                                     lines.
     * @param optional string $charset     Character set to use when breaking
     *                                     lines.
     * @param optional boolean $quote      Ignore lines that are wrapped with
     *                                     the '>' character (RFC 2646)? If
     *                                     true, we don't remove any padding
     *                                     whitespace at the end of the
     *                                     string.
     *
     * @return string  String containing the wrapped text.
     */
    function wrap($text, $length = 80, $break_char = "\n", $charset = null,
                  $quote = false)
    {
        $paragraphs = array();

        foreach (preg_split('/\r?\n/', $text) as $input) {
            if ($quote && (strpos($input, '>') === 0)) {
                $line = $input;
            } else {
                /* We need to handle the Usenet-style signature line
                 * separately; since the space after the two dashes is
                 * REQUIRED, we don't want to trim the line. */
                if ($input != '-- ') {
                    $input = rtrim($input);
                }
                $line = wordwrap($input, $length, $break_char);
            }

            $paragraphs[] = $line;
        }

        return implode($break_char, $paragraphs);
    }

    /**
     * Returns true if the every character in the parameter is an
     * alphabetic character. This method doesn't work with any charset
     * other than the current charset yet.
     *
     * @param $string   The string to test.
     * @param $charset  The charset to use when testing the string.
     *
     * @return boolean  True if the parameter was alphabetic only.
     */
    function isAlpha($string, $charset = null)
    {
        if (Util::extensionExists('mbstring')) {
            $old_charset = mb_regex_encoding();
            if ($charset != $old_charset) {
                @mb_regex_encoding($charset);
            }
            $alpha = !mb_ereg_match('[^[:alpha:]]', $string);
            if ($charset != $old_charset) {
                @mb_regex_encoding($old_charset);
            }
            return $alpha;
        }

        return ctype_alpha($string);
    }

    /**
     * Returns true if every character in the parameter is a lowercase
     * letter in the current locale.
     *
     * @access public
     *
     * @param $string   The string to test.
     * @param $charset  The charset to use when testing the string.
     *
     * @return boolean  True if the parameter was lowercase.
     */
    function isLower($string, $charset = null)
    {
        return ((String::lower($string, true, $charset) === $string) &&
                String::isAlpha($string, $charset));
    }

    /**
     * Returns true if every character in the parameter is an
     * uppercase letter in the current locale.
     *
     * @access public
     *
     * @param string $string   The string to test.
     * @param string $charset  The charset to use when testing the string.
     *
     * @return boolean  True if the parameter was uppercase.
     */
    function isUpper($string, $charset = null)
    {
        return ((String::upper($string, true, $charset) === $string) &&
                String::isAlpha($string, $charset));
    }

    /**
     * Performs a regex match search on the text provided.  Will correctly
     * handle text with multibyte characters if the mbstring extensions and
     * the mbregex functions are available.  Will use the preg_match()
     * function if possible or if the mbregex ereg function is not available.
     *
     * @access public
     * @since Horde 3.1
     *
     * @param string $text     The text to search.
     * @param array $regex     The regular expressions to use.  These
     *                         expressions should conform to ereg() rules -
     *                         extended perl rules are NOT supported.
     *                         Additionally, do NOT add perl regex delimiters
     *                         (e.g. '/' or '|') to the beginning/end.
     * @param string $charset  The character set of the text.
     *
     * @return array  The matches array from the first regex that matches.
     */
    function regexMatch($text, $regex, $charset = null)
    {
        static $mbregex;
        if (!isset($mbregex)) {
            $mbregex = function_exists('mb_ereg');
        }

        $use_mb = false;

        if ($mbregex && !is_null($charset) &&
            (String::lower($charset) != 'utf-8')) {
            $old_charset = mb_regex_encoding();
            if ($charset != $old_charset) {
                @mb_regex_encoding($charset);
            } else {
                unset($old_charset);
            }
            $use_mb = true;
        }

        $matches = array();

        foreach ($regex as $val) {
            if ($use_mb) {
                if (mb_ereg($val, $text, $matches)) {
                    break;
                }
            } else {
                if (preg_match('/' . $val . '/u', $text, $matches)) {
                    break;
                }
            }
        }
          
        if (isset($old_charset)) {
            @mb_regex_encoding($old_charset);
        }

        return $matches;
    }

}
