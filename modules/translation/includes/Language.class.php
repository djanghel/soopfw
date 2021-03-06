<?php

/**
 * This object is the main class which handles our translation
 * Here the translation files can be build and we can request a translation
 * for a given key
 *
 * @copyright Christian Ackermann (c) 2010 - End of life
 * @author Christian Ackermann <prdatur@gmail.com>
 * @category Module
 */
class Language extends Object
{
	/**
	 * The language items.
	 *
	 * @var array
	 */
	public $items = array();

	/**
	 * The language items for javascript.
	 *
	 * @var array
	 */
	public $items_js = array();

	/**
	 * Holds the country code translations
	 *
	 * @var array
	 */
	public $codes = array();

	/**
	 * Holds the currency translations
	 *
	 * @var array
	 */
	public $currencies = array();

	/**
	 * Holds the language translations
	 *
	 * @var array
	 */
	public $languages = array();

	/**
	 * The language (de,en etc)
	 * We get the strings back within this language
	 *
	 * @var string
	 */
	protected $language;

	/**
	 * The modules which are already loaded
	 *
	 * @var array
	 */
	protected $loaded_module_cats = array();

	/**
	 * The categories which are already loaded
	 *
	 * @var array
	 */
	protected $loaded_cats = array();
	
	/**
	 * The js categories which are already loaded
	 *
	 * @var array
	 */
	protected $js_loaded_cats = array();

	/**
	 * Constructor.
	 *
	 * @param string $language
	 *   The language we want to use. (optional, default = '')
	 * @param string &$core
	 *   The core object. (optional, default = null)
	 */
	function __construct($language = '', &$core = null) {
		parent::__construct($core);

		// Setup default language.
		if (!empty($this->core) && empty($language)) {
			$language = $this->core->default_language;
		}

		// Set the language.
		if (!empty($language)) {
			$this->set_lang($language);
		}
	}

	/**
	 * load the country list if needed.
	 *
	 * @param string $language
	 *   if set we load the list within this language else the current language will be used. (optional, default = '')
	 * @param array $push_countries_to_front
	 *   Whether to get specified elements at the top of the list. (optional, default = array())
	 */
	public function load_country_list($language = '', $push_countries_to_front = array()) {
		// Just load the list the first time.
		if (count($this->codes) > 0) {
			return;
		}

		// Load current countries.
		if ($this->load_list("countries", $language)) {
			$this->codes = array_change_key_case($this->codes, CASE_UPPER);
		}

		// If we want to push some countries to the top.
		if (!empty($push_countries_to_front)) {
			// Get the country codes.
			$codes = $this->codes;

			$merge_array = array();

			// Unset "preferred" countries within the current result array.
			foreach ($push_countries_to_front AS $language) {
				unset($codes[$language]);
				$merge_array[$language] = $this->codes[$language];
			}

			// Append a dummy line to indicate top elements are pushed to top, bottom are the normal ones.
			$merge_array[''] = '----------------------';

			// Add the preferred countries at the top of the list.
			$this->codes = array_merge($merge_array, $codes);
			unset($codes);
		}
	}

	/**
	 * Load the currencies list if needed.
	 *
	 * @param string $language
	 *   If set we load the list within this language else the current language will be used. (optional, default = '')
	 * @param array $push_countries_to_front
	 *   Whether to get specified elements at the top of the list. (optional, default = array())
	 */
	public function load_currency_list($language = '', $push_countries_to_front = array()) {
		// Just load the list the first time.
		if (count($this->currencies) > 0) {
			return;
		}

		// Load current currencies.
		if ($this->load_list("currencies", $language)) {
			$this->currencies = array_change_key_case($this->currencies, CASE_UPPER);
		}

		// If we want to push some currencies to the top.
		if (!empty($push_countries_to_front)) {
			// Get the country codes.
			$codes = $this->currencies;

			$merge_array = array();

			// Unset "preferred" currencies within the current result array.
			foreach ($push_countries_to_front AS $language) {
				unset($codes[$language]);
				$merge_array[$language] = $this->currencies[$language];
			}

			// Append a dummy line to indicate top elements are pushed to top, bottom are the normal ones.
			$merge_array[''] = '----------------------';

			// Add the preferred currencies at the top of the list.
			$this->currencies = array_merge($merge_array, $codes);
			unset($codes);
		}
	}

	/**
	 * Load the language list if needed.
	 *
	 * @param string $language
	 *   if set we load the list within this language else the current language will be used. (optional, default = '')
	 * @param array $push_countries_to_front
	 *   Whether to get specified elements at the top of the list. (optional, default = array())
	 * @param boolean $only_enabled
	 *   Whether we want just enabled languages or all. (optional, default = false)
	 */
	public function load_language_list($language = '', $push_countries_to_front = array(), $only_enabled = false) {

		// Load current languages.
		if ($this->load_list("languages", $language)) {
			$this->languages = array_change_key_case($this->languages, CASE_LOWER);
		}

		// If we want to push some currencies to the top.
		if (!empty($push_countries_to_front)) {
			// Get the country codes.
			$codes = $this->languages;

			$merge_array = array();

			// Unset "preferred" languages within the current result array.
			foreach ($push_countries_to_front AS $language) {
				unset($codes[$language]);
				$merge_array[$language] = $this->languages[$language];
			}

			// Append a dummy line to indicate top elements are pushed to top, bottom are the normal ones.
			$merge_array[''] = '----------------------';

			// Add the preferred languages at the top of the list.
			$this->languages = array_merge($merge_array, $codes);
			unset($codes);
		}

		// If we only want enabled languages.
		if ($only_enabled == true) {
			$return_array = array();

			// Get all enabled languages.
			foreach ($this->db->query_slave_all("SELECT * FROM `" . LanguagesObj::TABLE . "` WHERE `enabled` = 1") AS $language) {

				// If current language array did not have the database language skip it.
				if (!isset($this->languages[$language['lang']])) {
					continue;
				}

				// Add the language to the returning results.
				$return_array[$language['lang']] = $this->languages[$language['lang']];
			}

			// Override the languages.
			$this->languages = $return_array;
		}
	}

	/**
	 * Set the language and load system translations for that language
	 *
	 * @param string $lang
	 *   The language key.
	 */
	public function set_lang($lang) {
		$this->language = strtolower($lang);
		$this->load('_system');
		$this->load_javascript('_system');
	}

	/**
	 * Get the current language
	 *
	 * @return string The language key.
	 */
	public function get_lang() {
		return $this->language;
	}

	/**
	 * Returns a locale string for our language in form of language-LANGUAGE
	 * for example: de-DE
	 *
	 * @return string
	 */
	public function get_locale() {
		return strtolower($this->language) . '-' . strtoupper($this->language);
	}

	/**
	 * Format a currency number.
	 *
	 * @param int $number
	 *   The number.
	 *
	 * @return string the formated number
	 */
	public function format_currency($number) {
		return number_format($number, 2, '.', '\'');
	}

	/**
	 * Load specific javascript translations.
	 *
	 * @param string $catlist
	 *   The categories to be loaded, mutliple seperated by comma.
	 */
	public function load_javascript($catlist) {

		// Get all categories.
		$cats = explode(",", $catlist);
		
		// Process all categories.
		foreach ($cats as $tmp_cat) {
			// Do not load it twice.
			if (in_array($tmp_cat, $this->js_loaded_cats)) {
				continue;
			}

			// Mark it has loaded.
			$this->js_loaded_cats[] = $tmp_cat;

			if (file_exists(SITEPATH . "/language/" . $this->language . "/" . $tmp_cat . "_js.php")) {
				include(SITEPATH . "/language/" . $this->language . "/" . $tmp_cat . "_js.php");
			}
			if (file_exists(SITEPATH . "/modules/" . $tmp_cat . "/language/" . $this->language . "_js.php")) {
				include(SITEPATH . "/modules/" . $tmp_cat . "/language/" . $this->language . "_js.php");
			}
		}
	}

	/**
	 * Load the language file.
	 *
	 * @param string $catlist
	 *   The categories to be loaded, multiple seperated by a "," (comma).
	 */
	function load($catlist) {

		// Get all categories.
		$cats = explode(",", $catlist);

		// Process all categories.
		foreach ($cats as $tmp_cat) {
			// Do not load it twice.
			if (in_array($tmp_cat, $this->loaded_cats)) {
				continue;
			}

			// Mark it has loaded.
			$this->loaded_cats[] = $tmp_cat;

			if (file_exists(SITEPATH . "/language/" . $this->language . "/" . $tmp_cat . ".php")) {
				$tmp = $this->items;
				include(SITEPATH . "/language/" . $this->language . "/" . $tmp_cat . ".php");
				$this->items = $tmp + $this->items;
			}

			if (file_exists(SITEPATH . "/modules/" . $tmp_cat . "/language/" . $this->language . ".php")) {
				$tmp = $this->items;
				include(SITEPATH . "/modules/" . $tmp_cat . "/language/" . $this->language . ".php");
				$this->items = $tmp + $this->items;
			}
		}

		// If no entries exist, return false.
		if (empty($this->items)) {
			return false;
		}
	}

	/**
	 * Get a language item.
	 *
	 * @param string $item_name
	 *   The item name.
	 * @param string $index
	 *   The index if $itemname == array(), if left empty all entries will be returned. (optional, default = '')
	 * @param array $replacement
	 *   Replaces the key-strings with the array value for that key. (optional, default = array())
	 *
	 * @return string Translation of this element.
	 */
	public function get($item_name, $index = "", $replacement = array()) {
		
		// Check if we have this item.
		if (isset($this->items[$item_name])) {

			// Check if the items are arrays and we got a provided $index.
			if (is_array($this->items[$item_name]) && !empty($index)) {

				// Check if the array index exist within the translation.
				if (!isset($this->items[$item_name][$index])) {
					if ($this->core->get_debug()) {
						trigger_error("Tried to get language key " . $item_name . " with index " . $index . " which is not set", E_USER_NOTICE);
					}
					return "";
				}

				// Return the translation.
				return $this->items[$item_name][$index];
			}

			// Check if we should do replacements within the translation.
			if (count($replacement) > 0) {
				$var = $this->items[$item_name];
				foreach ($replacement AS $k => $v) {
					$var = str_replace($k, $v, $var);
				}
				return $var;
			}
			return $this->items[$item_name];
		}
		else if ($this->core->get_debug()) {
			echo "missing langKey::" . $item_name;
		}
	}

	/**
	 * Get all language items for javascript.
	 *
	 * @return array Translation of all stored words as key=>val array.
	 */
	public function get_all_js() {
		return $this->items_js;
	}

	/**
	 * Builds the language files or returns the translation keys.
	 *
	 * @param mixed $modules
	 *   The modules or a single module as a string which we want to search, if not provided all modules will be used (optional, default = array()
	 * @param mixed $language_array
	 *   The language_array or a single language as a string which we want to read, if not provided all enabled languages will be used (optional, default = array())
	 * @param boolean $return_values
	 *   Whether we want to store the files or we want to return the values (optional, default = false)
	 * @param boolean $read_translations
	 *   Whether we should read the translation for the current language or not (optional, default = true)
	 * @param array &$errors
	 *   This array will be filled with all occured errors (optional, default = array())
	 *
	 * @return array if $return_values is set to true we get the array, else nothing will be returnd
	 */
	public function build_language($modules = array(), $language_array = array(), $return_values = false, $read_translations = true, &$errors = array()) {
		global $translation_cache;

		// Holds all already translated keys (we do not want to translate the same key twice).
		$already_translated = array();
		$already_translated_js = array();

		// Init our error array.
		$errors = array();

		// Init the return array.
		$return_array = array();

		// Transform language array to an array if provided but only a single string.
		if (!is_array($language_array) && !empty($language_array)) {
			$language_array = array($language_array);
		}

		// Get all enabled languages if we did not provide a language array.
		if (empty($language_array)) {
			$language_array = $this->get_enabled_languages();
		}

		// Ttransform modules array to an array if provided but only a single string.
		if (!is_array($modules) && !empty($modules)) {
			$modules = array($modules);
		}

		// Get all modules if we did not provide it.
		if (empty($modules)) {
			$modules = $this->core->modules;
			$modules['system_core'] = "system_core";
		}

		$php_results = $js_results = $smarty_results = array();

		// Loop through all modules to get php, smarty and javascript t calls, php and smarty will be merged couse they call always php t function.
		foreach ($modules AS $module) {
			$already_translated = array();
			$already_translated_js = array();
			if ($module == "system_core") {

				// If we want to store the file check if path is writeable.
				if ($return_values != true && !is_writeable(SITEPATH . "/language")) {
					$errors[] = "Path: " . SITEPATH . "/language not writeable\n";
					continue;
				}

				// Scan directories for files php and js files within core dirs.
				$dir = new Dir("/");
				$dir->dir_regexp("^\/?(\/lib|\/js|\/cli|\/templates)");
				$dir->skip_dirs("/templates_c");
			}
			else {
				// Create all needed directories for storing files if we want it.
				if ($return_values != true && !is_dir(SITEPATH . "/modules/" . $module . "/language")) {
					if (!is_writeable(SITEPATH . "/modules/" . $module)) {
						$errors[] = "Path: " . SITEPATH . "/modules/" . $module . " could not create language directory\n";
						continue;
					}
					mkdir(SITEPATH . "/modules/" . $module . "/language");
				}

				// If we want to store the file check if path is writeable.
				if ($return_values != true && !is_writeable(SITEPATH . "/modules/" . $module . "/language")) {
					$errors[] = "Path: " . SITEPATH . "/modules/" . $module . "/language not writeable\n";
					continue;
				}

				// Scan directories for files php and js files.
				$dir = new Dir("/modules/" . $module);
				$dir->skip_dirs_regexp(".*\/language\/.*");
			}

			$dir->just_files();
			$dir->file_regexp("(php|js|tpl)$");

			/**
			 * We should only regenerate results arrays if we build up files, so that different modules can have same translations, but in return mode
			 * we want only unique translations
			 */
			if ($return_values != true) {
				$php_results = $js_results = $smarty_results = array(); //$smarty_results just for faster debugin so its always declared
			}

			foreach ($dir AS $entry) {
				// File is javascript so parse it like that.
				if ($entry->ext == "js") {
					$js_results = ArrayTools::array_extend($js_results, $this->get_js_strings_by_file($entry->path));
				}
				// File is template, parse as smarty but extend it to php couse it calls also the php t function.
				else if ($entry->ext == "tpl") {
					$php_results = ArrayTools::array_extend($php_results, $this->get_smarty_strings_by_file($entry->path));
				}
				// Parse it as a php file.
				else {
					$php_results = ArrayTools::array_extend($php_results, $this->get_strings_by_file($entry->path));
				}
			}
			
			// Stop here because we do only want to return the values.
			if ($return_values == true) {
				continue;
			}

			$languages = $language_array;
			$objectsloaded = array();

			// Loop through all wanted languages.
			foreach ($languages AS $language => $language_label) {
				$language = strtolower($language);
				// If we build system_core languages we have a different filepath for translation.
				if ($module == "system_core") {
					if (!is_dir(SITEPATH . "/language/" . $language)) {
						mkdir(SITEPATH . "/language/" . $language);
					}
					$save_path = SITEPATH . "/language/" . $language . "/_system.php";
					$save_path_js = SITEPATH . "/language/" . $language . "/_system_js.php";
				}
				else {
					$save_path = SITEPATH . "/modules/" . $module . "/language/" . $language . ".php";
					$save_path_js = SITEPATH . "/modules/" . $module . "/language/" . $language . "_js.php";
				}

				
				$lines = array();
				
				// Loop through all found javascript translations calls
				foreach ($js_results AS $id => $key) {
					$key = preg_replace("/\"/", '\"', preg_replace("/\\\\\"/", '"', strtolower($key)));
					// Add current translation to the translation cache, so that our register shutdown function can add the entries which are not added yet.
					$translation_cache[md5($key)] = $key;

					// Do not try to read the translation if we do not want the translation inclusion.
					if ($read_translations == false) {
						$lines[] = "\$this->items_js[\"" . $key . "\"] = \"\";";
						continue;
					}

					// Do not translate same keys twice or more.
					if (isset($already_translated_js[$id . "_" . $language])) {
						continue;
					}

					// If we did not load the translation for this id load it.
					if (!isset($objectsloaded[$id])) {
						$keys = array();
						
						// Build up the primary keys to load with load multiplie so 4times less mysql queries are send.
						foreach ($languages AS $tmplanguage => $label) {
							$keys[] = array($id, $tmplanguage);
						}
						
						// Load it.
						
						$translation_tmp_obj = new TranslationObj();
						foreach ($translation_tmp_obj->load_multiple($keys, PDT_ARR) AS $row) {
							$objectsloaded[$id][$row['id'] . ":" . $row['language']] = $row;
						}						
					}
					$translation_obj = array();
					// Check if we already have loaded this id with this language.
					if (isset($objectsloaded[$id][$id . ":" . $language])) {
						$translation_obj = $objectsloaded[$id][$id . ":" . $language];
					}

					// If all caches fail we must load it from db or fill it with default values.
					if (empty($translation_obj)) {
						$translation_tmp_obj = new TranslationObj($id, $language);
						$translation_obj = $translation_tmp_obj->get_values();

						if (empty($translation_obj)) {
							$translation_tmp_obj->set_default_fields();
							$translation_obj = $translation_tmp_obj->get_values();
						}
					}
					// New behaviour will not remove primary key within failed object loadings, so check if we have really found a translation.
					if (!isset($translation_obj['translation'])) {
						continue;
					}
					// Add loaded object to our cache.
					$already_translated_js[$id . "_" . $language] = $translation_obj['translation'];

					// Add the line for building.
					$lines[] = "\$this->items_js[\"" . $key . "\"] = \"" . $translation_obj['translation'] . "\";";
				}
				// Write JS Files.
				file_put_contents($save_path_js, "<?php\n" . implode("\n", $lines) . "\n?>");
				
				// Same as above with php_results.
				$lines = array();
				foreach ($php_results AS $id => $key) {
					$key = preg_replace("/\"/", '\"', preg_replace("/\\\\\"/", '"', strtolower($key)));
					$translation_cache[md5($key)] = $key;
					if ($read_translations == false) {
						$lines[] = "\$this->items[\"" . $key . "\"] = \"\";";
						continue;
					}
					if (isset($already_translated[$id . "_" . $language])) {
						continue;
					}
					$translation_obj = array();
					if (!isset($objectsloaded[$id])) {
						$keys = array();
						foreach ($languages AS $tmplanguage => $label) {
							$keys[] = array($id, $tmplanguage);
						}
						$translation_tmp_obj = new TranslationObj();
						foreach ($translation_tmp_obj->load_multiple($keys, PDT_ARR) AS $row) {
							$objectsloaded[$id][$row['id'] . ":" . $row['language']] = $row;
						}
					}
					
					if (isset($objectsloaded[$id][$id . ":" . $language])) {
						$translation_obj = $objectsloaded[$id][$id . ":" . $language];
					}
					if (empty($translation_obj)) {
						$translation_tmp_obj = new TranslationObj($id, $language);
						$translation_obj = $translation_tmp_obj->get_values();
						
						if (empty($translation_obj)) {
							$translation_tmp_obj->set_default_fields();
							$translation_obj = $translation_tmp_obj->get_values();
						}
					}
					
					if (!isset($translation_obj['translation'])) {
						continue;
					}
					$already_translated[$id . "_" . $language] = $translation_obj['translation'];
					$lines[] = "\$this->items[\"" . $key . "\"] = \"" . $translation_obj['translation'] . "\";";
				}

				file_put_contents($save_path, "<?php\n" . implode("\n", $lines) . "\n?>");
			}
		}

		// If we only want to return the values.
		if ($return_values == true) {
			// <merge our php and js values into one array.
			$php_results = ArrayTools::array_extend($php_results, $js_results);
			// Unset unneeded values (better to memory).
			unset($js_results);

			// In return mode we only want one language. so get the first and only value from language.
			reset($language_array);
			$selected_language = current($language_array);

			$translation_tmp_obj = new TranslationObj();
			// Loop through our results.
			foreach ($php_results AS $id => $key) {
				$key = preg_replace("/\"/", '\"', preg_replace("/\\\\\"/", '"', strtolower($key)));
				$translation_cache[md5($key)] = $key;

				// Do not try to read the translation if we do not want the translation inclusion.
				if ($read_translations == false) {
					$return_array[$key] = "";
					continue;
				}

				// Try to load the translation.
				$translation_tmp_obj->load(array($id, $selected_language));
				$return_array[$key] = $translation_tmp_obj->translation;
			}

			// Return values.
			return $return_array;
		}
	}

	/**
	 * Returns all enabled languages
	 *
	 * @return array The language list with only enabled language.
	 */
	public function get_enabled_languages() {
		$return = array();
		$this->load_language_list();
		if (empty($this->languages)) {
			return array();
		}
		foreach ($this->db->query_slave_all("SELECT `lang` FROM `" . LanguagesObj::TABLE . "` WHERE `enabled` = 1") AS $row) {
			$return[$row['lang']] = $this->languages[$row['lang']];
		}
		return $return;
	}

	/**
	 * Parse the given file for php t function calls.
	 *
	 * @param string $file
	 *   Full path to file.
	 */
	private function get_strings_by_file($file) {
		/**
		 * Get all t functions native string from given file
		 * In match array key index 2 there are "-chars if the t function starts with " so we can determine our primary start quote type
		 */
		preg_match_all("/[\s\(\.]t\(\s*((\").*[^\\\\]\"|'.*[^\\\\]'|\s*$)\s*(\)|,\s*array\()/iUs", file_get_contents($file), $matches);
		$translation_strings = array();

		foreach ($matches[1] AS $index => $text) {

			// If index 2 is not empty, its starts with " else it starts with '.
			$start_quote = (!empty($matches[2][$index])) ? '"' : "'";

			// Our primary quote counter.
			$c = 0;

			// The array which we will fill up until we have even count.
			$comma_array = array();

			// Explode our orig string by comma to determine if t function was called with more than 1 parameter.
			foreach (explode(",", $text) AS $substr) {

				// replace all escaped primary quote type chars, so that the primary quotes which are left are the ones which are not escaped.
				$substr_replaced = str_replace("\\" . $start_quote, "", $substr);

				// Preg match all primary quote types to determine current quote char count
				preg_match_all("/" . $start_quote . "/is", $substr_replaced, $fl_array);

				// Add quote count to our comma quote counter
				$c += count($fl_array[0]);

				// Add this line to our comma array which will have all elements which determines the real translation string.
				$comma_array[] = $substr;

				// Check if the last step provided us an even comma count, if it is even than break. It must also be greater than 0. we are finish with our "first parameter"  of the t function which is our translation text.
				if ($c > 0 && $c % 2 == 0) {
					break;
				}
			}

			// Implode our comma array to a string back.
			$t_string = implode(",", $comma_array);

			// Add the string without starting and ending quotes to the result array.
			$string = str_replace("\\'", "'", strtolower(substr($t_string, 1, strlen($t_string) - 2)));
			$translation_strings[md5($string)] = $string;
		}

		// Return the strings.
		return $translation_strings;
	}

	/**
	 * Parse the given file for smarty t function calls.
	 *
	 * @param string $file
	 *   Full path to file.
	 */
	private function get_smarty_strings_by_file($file) {
		preg_match_all("/<%t\s*key\=(\".*\"|'.*')(\s*args\=(\[.*\]|\".*\"|'.*'))?%>/iUs", file_get_contents($file), $matches);
		$translation_strings = array();
		foreach ($matches[1] AS $trans) {
			$string = str_replace("\\'", "'", strtolower(substr($trans, 1, strlen($trans) - 2)));
			$translation_strings[md5($string)] = $string;
		}
		return $translation_strings;
	}

	/**
	 * Parse the given file for javascript t function calls.
	 *
	 * @param string $file
	 *   Full path to file.
	 */
	private function get_js_strings_by_file($file) {
		$translation_strings = array();
		$code = file_get_contents($file);
		$js_string_regex = '(?:(?:\'(?:\\\\\'|[^\'])*\'|"(?:\\\\"|[^"])*")(?:\s*\+\s*)?)+';
		preg_match_all('~[^\w]Soopfw\s*\.\s*t\s*\(\s*(' . $js_string_regex . ')\s*[,\)]~s', $code, $t_matches, PREG_SET_ORDER);
		if (isset($t_matches) && count($t_matches)) {
			foreach ($t_matches as $match) {
				// Remove match from code to help us identify faulty Soopfw.t() calls.
				$code = str_replace($match[0], '', $code);

				$string = strtolower($this->format_quoted_string(implode('', preg_split('~(?<!\\\\)[\'"]\s*\+\s*[\'"]~s', $match[1]))));
				$translation_strings[md5($string)] = $string;
			}
		}
		return $translation_strings;
	}

	/**
	 * Escape quotes in a strings depending on the surrounding quote type used.
	 *
	 * @param string $str
	 *   The strings to escape.
	 *
	 * @return string formatted string.
	 */
	private function format_quoted_string($str) {
		$quo = substr($str, 0, 1);
		$str = substr($str, 1, -1);
		if ($quo == '"') {
			$str = stripcslashes($str);
		}
		else {
			$str = strtr($str, array("\\'" => "'", "\\\\" => "\\"));
		}
		return addcslashes($str, "\0..\37\\\"");
	}

	/**
	 * Load the given list for the given language.
	 *
	 * @param string $type
	 *   Tthe list type.
	 * @param string $language
	 *   If set we load the list within this language else the current language will be used. (optional, default = '')
	 *
	 * @return boolean if load succeed true, else false
	 */
	private function load_list($type, $language = '') {
		// Get current language if we do provided a specific one.
		if (empty($language)) {
			$language = $this->get_lang();
		}
		// Get the file path.
		$fn = SITEPATH . '/language/' . $type . '/' . $language . '.php';

		// Check if file exists.
		if (@!file_exists($fn)) {
			return false;
		}
		// Load the list.
		require($fn);
		return true;
	}

}

