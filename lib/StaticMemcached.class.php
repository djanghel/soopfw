<?php

/**
 * Provides a memcached wrapper with a static engine
 * Beware we can NOT use code design (underscore only) within the public methods
 * because these must equals the original Memcached class
 *
 * Special not on this wrapper, it will NOT cache anything, will only cache for runtime
 *
 * @copyright Christian Ackermann (c) 2010 - End of life
 * @author Christian Ackermann <prdatur@gmail.com>
 * @package lib
 */
class StaticMemcached extends Object
{
	/**
	 * Holds the last result code
	 * @var int
	 */
	private $last_result_code;

	/**
	 * Performance feature, all deletes will be added to an array and only deleted after the object will be destroyed
	 * @var array
	 */
	private $delete_list = array();

	/**
	 * The values
	 * @var array
	 */
	private $values = array();

	/**
	 * The prefix for keys.
	 *
	 * @var string
	 */
	private $prefix_key = "";

	/**
	 * Construct
	 *
	 * @param Core $core
	 */
	public function __construct(&$core) {
		parent::__construct($core);
		$this->last_result_code = Memcached::RES_SUCCESS;
	}

	/**
	 * Destructor, will delete all needed entries
	 */
	function __destruct() {
		foreach ($this->delete_list as $key) {
			$this->delete($key);
		}
	}

	/**
	 * Set an option
	 *
	 * @param string $option
	 * @param mixed $value
	 * @return boolean true
	 */
	public function setOption($option, $value) {
		switch ($option) {
			case Memcached::OPT_PREFIX_KEY:
				$this->prefix_key = substr($value, 0, 128);
				break;
		}
		$this->last_result_code = Memcached::RES_SUCCESS;
		return true;
	}

	/**
	 * Add a server
	 *
	 * @param string $host
	 * @param int $port
	 * @param int $weight
	 * @return boolean true
	 */
	public function addServer($host, $port, $weight = 0) {
		$this->last_result_code = Memcached::RES_SUCCESS;
		return true;
	}

	/**
	 * Store an item
	 * @param string $key The key under which to store the value.
	 * @param mixed $value The value to store.
	 * @param int $expiration The expiration time, defaults to 0. See Expiration Times for more info.  (optional, default = 0)
	 */
	public function set($key, $value, $expiration = 0) {
		$expiration = (int) $expiration;
		if ($expiration > 0 && $expiration < 2592000) {
			$expiration += time();
		}
		$this->values[$this->prefix_key . $key] = array(
			'expires' => $expiration,
			'value' => serialize($value)
		);
		$this->last_result_code = Memcached::RES_SUCCESS;
		return true;
	}

	/**
	 * Increment the given value for the key with the given offset
	 *
	 * @param string $key the cache key
	 * @param int $offset the int to be incremented
	 * @return boolean true on success, else false
	 */
	public function increment($key, $offset) {
		if (isset($this->values[$this->prefix_key . $key])) {
			$this->values[$this->prefix_key . $key] += (int) $offset;
		}
		return true;
	}

	/**
	 * Decrement the given value for the key with the given offset
	 *
	 * @param string $key the cache key
	 * @param int $offset the int to be decremented
	 * @return boolean true on success, else false
	 */
	public function decrement($key, $offset) {
		if (isset($this->values[$this->prefix_key . $key])) {
			$this->values[$this->prefix_key . $key] -= (int) $offset;
		}
		return true;
	}

	/**
	 * Retrieve an item
	 * @param string $key The key under which to store the value.
	 * @return mixed Returns the value of the item or false on error
	 */
	public function get($key, $cache_db = null, &$cas_token = '') {
		//First set the return code to an error
		$this->last_result_code = Memcached::RES_FAILURE;

		//Check if we have a value for that key
		if (!isset($this->values[$this->prefix_key . $key])) {
			return null;
		}

		$res = $this->values[$this->prefix_key . $key];

		//Check if it was expired
		if (!$this->check_expire($res)) {
			$this->last_result_code = Memcached::RES_NOTFOUND;
			return null;
		}

		//All went fine, set the success return code and return the value
		$this->last_result_code = Memcached::RES_SUCCESS;
		return unserialize($res['value']);
	}

	/**
	 * Return the result code of the last operation
	 * @return int returns one of the Memcached::RES_* constants that is the result of the last executed Memcached method.
	 */
	public function getResultCode() {
		return $this->last_result_code;
	}

	/**
	 * Retrieve multiple items
	 *
	 * @param array $key The key under which to store the value.
	 * @return array Returns the array of found items or false on error
	 */
	public function getMulti($keys) {
		//First set the return code to an error
		$this->last_result_code = Memcached::RES_FAILURE;
		$return = array();

		$rows = array();

		//try to get the values
		foreach ($keys as $value) {
			if (isset($this->values[$this->prefix_key . $value])) {
				$rows[$value] = $this->values[$this->prefix_key . $value];
			}
		}

		if (empty($rows)) {
			return false;
		}

		//Loop through all found values and check if some expired
		foreach ($rows AS $key => $res) {
			if (!$this->check_expire($res)) {
				continue;
			}
			$return[$key] = unserialize($res['value']);
		}

		//All went fine, set the success return code and return the value
		$this->last_result_code = Memcached::RES_SUCCESS;
		return $return;
	}

	/**
	 * Store multiple item
	 * @param array $items One or more key/value pair/s which to store
	 * @param int $expiration The expiration time, defaults to 0. See Expiration Times for more info.  (optional, default = 0)
	 */
	public function setMulti($values, $expiration = 0) {
		foreach ($values as $key => $value) {
			$this->set($key, $value, $expiration);
		}
		$this->last_result_code = Memcached::RES_SUCCESS;
		return true;
	}

	/**
	 * Delete an item
	 * @param string $key The key to be deleted.
	 * @param int $time The amount of time the server will wait to delete the item.  (optional, default = 0)
	 * @return boolean Returns true on success, else false.
	 */
	public function delete($key) {
		if (isset($this->values[$this->prefix_key . $key])) {
			unset($this->values[$this->prefix_key . $key]);
		}
		return true;
	}

	/**
	 * Returns if a given result row is not expired
	 *
	 * @param array $res the result row from database
	 * @return boolean false if expired, else true
	 */
	private function check_expire($res) {
		if (empty($res['expires'])) {
			return true;
		}
		if ($res['expires'] > time()) {
			return true;
		}
		$this->delete_list[] = $res['key'];
		return false;
	}

	/**
	 * Flush all existing items at the server
	 * @return boolean Returns true on success, else false.
	 */
	public function flush() {
		$this->values = array();
		return true;
	}

}