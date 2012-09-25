<?php
/**
 * Translate the given key, which should be always the english part of the translation.
 *
 * @param string $key
 *   the language key
 * @param array $args
 *   an array with replacement array('search_key' => 'replace_value')
 * 	 We can use this prefix in char in front of the search_key
 * 	 i = intval, f = floatval, all other search_key's will be replaced with htmlspecialchars
 *
 * @return string the translated string, or if the translations is not found return the given key
 */
function t($key, $args = array()) {
	static $key_cache = array();
	global $translation_cache;

	$core = Core::get_instance();
	$save_key = strtolower($key);

	// Sort the arg array descending by strlen.
	if (!empty($args)) {
		uksort($args, function($a, $b) {
					$str_len_a = strlen($a) - 1;
					if (!isset($b{$str_len_a})) {
						return 1;
					}

					if (!isset($b{$str_len_a + 1})) {
						return -1;
					}

					return 0;
				});
	}
	$cache_key = $save_key . "|" . md5(json_encode($args));
	if (isset($key_cache[$cache_key])) {
		return $key_cache[$cache_key];
	}

	//$core = &$GLOBALS['core'];
	$bbcode = new BBCodeParser();


	//Check if language is available
	if (!empty($core->lng)) {

		//Try to get the translation for the key and do replacements within language object
		$translated = $core->lng->get($save_key, "", $args);
		if (!empty($translated)) {
			$key = $translated;
		}
		else {
			$translation_cache[md5($save_key)] = $save_key;
		}
	}
	if (!empty($core)) {
		$cached_parsed = $core->mcache("core_translation_parsed:" . md5($key));
		if (empty($cached_parsed)) {
			$m_key = md5($key);
			//Parse bbcode
			$key = $bbcode->parse($key);
			$core->mcache("core_translation_parsed:" . $m_key, $key);
		}
		else {
			$key = $cached_parsed;
		}
	}
	else {
		$key = $bbcode->parse($key);
	}

	//We do not have found the translation so do the replacement on the english key string
	foreach ($args AS $k => $v) {
		switch (substr($k, 0, 1)) {
			case 'i': $v = (int) $v;
				break;
			case 'f': $v = (float) $v;
				break;
			case '!': break;
			default: $v = htmlspecialchars($v);
				break;
		}

		$key = str_replace($k, $v, $key);
	}
	$key_cache[$cache_key] = $key;
	//Return replaced untranslated english string
	return $key;
}

