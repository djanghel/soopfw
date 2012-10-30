<?php

/**
 * Provides an ajax return handler<br />
 * This needed for ajax action to return needed json encoded string
 *
 * @copyright Christian Ackermann (c) 2010 - End of life
 * @author Christian Ackermann <prdatur@gmail.com>
 * @category Ajax
 */
abstract class AjaxModul extends Object
{
	/**
	 * Define constances
	 */
	const SUCCESS = 200;
	const SUCCESS_NO_CHANGES = 201;

	const ERROR_INVALID_PARAMETER = 405;
	const ERROR_MISSING_PARAMETER = 406;
	const ERROR_DATABASE_ERROR = 407;

	const ERROR_DEFAULT = 501;
	const ERROR_MODULE_NOT_FOUND = 502;
	const ERROR_SECURITY_LOCK = 550;

	const ERROR_NOT_LOGGEDIN = 602;
	const ERROR_NO_RIGHTS = 601;

	/**
	 * Will return an array with <br />
	 * array("code" => $code, "desc" => $desc, "data" => $data)
	 * if $die is set to true it will print out the json encoded string for that array
	 * and directly die
	 *
	 * @param int $code
	 *   the return code, use one AjaxModul::*
	 * @param mixed $data
	 *   the additional data to be returned (optional, default = null)
	 * @param boolean $die
	 *   wether to output the data and die or to return the data (optional, default = true)
	 * @param string $desc
	 *   the error description (optional, default = '')
	 *
	 * @return array the returning array with format array("code" => $code, "desc" => $desc, "data" => $data)
	 */
	static function return_code($code, $data = null, $die = true, $desc = "") {
		switch ((int)$code) {
			default: $return = array("code" => $code, "desc" => $desc, "data" => $data);
		}
		if ($die == true) {
			echo json_encode($return);
			die();
		}
		return $return;
	}

	/**
	 * This function will be executed after ajax file initializing
	 */
	abstract function run();

}