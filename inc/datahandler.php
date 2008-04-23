<?php
/**
 * MyBB 1.4
 * Copyright � 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

/**
 * Base data handler class.
 *
 */
class DataHandler
{
	/**
	 * The data being managed by the data handler
	 *
	 * @var array Data being handled by the data handler.
	 */
	var $data = array();

	/**
	 * Whether or not the data has been validated. Note: "validated" != "valid".
	 *
	 * @var boolean True when validated, false when not validated.
	 */
	var $is_validated = false;

	/**
	 * The errors that occurred when handling data.
	 *
	 * @var array
	 */
	var $errors = array();

	/**
	 * The status of administrator override powers.
	 *
	 * @var boolean
	 */
	var $admin_override = false;

	/**
	 * Defines if we're performing an update or an insert.
	 *
	 * @var string
	 */
	var $method;

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	var $language_prefix = '';


	/**
	 * Constructor for the data handler.
	 *
	 * @param string The method we're performing with this object.
	 */
	function Datahandler($method="insert")
	{
		if($method != "update" && $method != "insert")
		{
			die("A valid method was not supplied to the data handler.");
		}
		$this->method = $method;
	}

	/**
	 * Sets the data to be used for the data handler
	 *
	 * @param array The data.
	 */
	function set_data($data)
	{
		if(!is_array($data))
		{
			return false;
		}
		$this->data = $data;
		return true;
	}

	/**
	 * Add an error to the error array.
	 *
	 * @param string The error name.
	 */
	function set_error($error, $data='')
	{
		$this->errors[] = array(
			"error_code" => $error,
			"data" => $data
		);
	}

	/**
	 * Returns the error(s) that occurred when handling data.
	 *
	 * @return string|array An array of errors.
	 */
	function get_errors()
	{
		return $this->errors;
	}

	/**
	 * Returns the error(s) that occurred when handling data
	 * in a format that MyBB can handle.
	 *
	 * @return An array of errors in a MyBB format.
	 */
	function get_friendly_errors()
	{
		global $lang;

		// Load the language pack we need
		if($this->language_file)
		{
			$lang->load($this->language_file, true);
		}
		// Prefix all the error codes with the language prefix.
		foreach($this->errors as $error)
		{
			$lang_string = $this->language_prefix.'_'.$error['error_code'];
			if(!$lang->$lang_string)
			{
				$errors[] = $error['error_code'];
				continue;
			}

			if(is_array($error['data']))
			{
				array_unshift($error['data'], $lang->$lang_string);
				$errors[] = call_user_func_array(array($lang, "sprintf"), $error['data']);
			}
			else
			{
				$errors[] = $lang->$lang_string;
			}
		}
		return $errors;
	}

	/**
	 * Sets whether or not we are done validating.
	 *
	 * @param boolean True when done, false when not done.
	 */
	function set_validated($validated = true)
	{
		$this->is_validated = $validated;
	}

	/**
	 * Returns whether or not we are done validating.
	 *
	 * @return boolean True when done, false when not done.
	 */
	function get_validated()
	{
		if($this->is_validated == true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/**
	* Verifies if yes/no options haven't been modified.
	*
	* @param array The user options array.
	* @param string The specific option to check.
	* @param string Optionally specify if the default should be used.
	*/
	function verify_yesno_option(&$options, $option, $default=1)
	{
		if($this->method == "insert" || array_key_exists($option, $options))
		{
			if($options[$option] != $default && $options[$option] != "")
			{
				if($default == 1)
				{
					$options[$option] = 0;
				}
				else
				{
					$options[$option] = 1;
				}
			}
			else if(@array_key_exists($option, $options) && $options[$option] == '')
			{
				$options[$option] = 0;
			}
			else
			{
				$options[$option] = $default;
			}
		}
	}
}
?>