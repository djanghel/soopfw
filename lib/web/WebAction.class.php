<?php

/**
 * Provides a class to handle web actions like ajax, ajax html or normal page calls
 *
 * @copyright Christian Ackermann (c) 2010 - End of life
 * @author Christian Ackermann <prdatur@gmail.com>
 * @package lib.web
 * @category Web
 */
class WebAction extends Object
{
	/**
	 * The parameters to determine which action we should call.
	 *
	 * @var array
	 */
	protected $action_params = array();

	public function process_action() {
		$this->init();
		$this->setup_params();
		$this->check_login();
		$this->switch_action_type();
	}

	/**
	 * Main initializing of an action.
	 */
	private function init() {
		ob_implicit_flush();
		header('Content-Type: text/html; charset=utf-8');
	}

	/**
	 * Setup call params.
	 */
	private function setup_params() {
		// Parse our request uri
		$override_params = UrlAliasObj::parse_url_string();
		$language = $override_params['language'];
		unset($override_params['language']);

		// Now we have the required language which is needed to boot up the core.
		// So we do it.
		$this->core->boot($language);

		// Check if we are on an administration link, if so ssl is required (if available).
		$this->core->cache('core', 'admin_theme', $override_params['admin_link']);
		if ($override_params['admin_link'] === true) {
			$this->core->need_ssl();
		}

		if ($this->core->cache('core', 'admin_theme')) {
			$this->smarty->set_tpl(SITEPATH . "/templates/" . $this->core->get_dbconfig("system", system::CONFIG_ADMIN_THEME, 'standard') . "/");
		}
		else {
			$this->smarty->set_tpl(SITEPATH . "/templates/" . $this->core->get_dbconfig("system", system::CONFIG_DEFAULT_THEME, 'standard') . "/");
		}

		// Get params from a maybe existing alias for current uri.
		$alias_params = UrlAliasObj::get_alias_from_uri($override_params);
		if ($alias_params !== false) {
			$override_params = array_merge($override_params, $alias_params);
		}


		$additional_function_params = $override_params['additional_function_params'];
		unset($override_params['additional_function_params']);

		// Prevent predefining perms within _GET request.
		if (isset($_GET['perm'])) {
			unset($_GET['perm']);
		}

		// Override the $_GET params with our override params.
		$param_array = array_merge($_GET, $override_params);

		// If we requested a logout, log the user out.
		if (!empty($_REQUEST['logout'])) {
			$this->session->logout();
		}

		#####################################################
		//Build the param array for the param struct
		$params = new ParamStruct();
		$params->add_param("module", PDT_STRING, "");
		$params->add_param("action", PDT_STRING, "");
		$params->add_param("popup", PDT_INT, 0);
		$params->add_param("dialog", PDT_INT, 0);
		$params->add_param("type", PDT_STRING, "");
		$params->add_param("file", PDT_STRING, "");
		$params->add_param("ajax_module", PDT_STRING, "");

		//This is only a filter, if the perm is not provided it will not allow security things, also it will always be overriden by alias perm definitions
		$params->add_param("perm", PDT_STRING, "");

		$params->fill($param_array);

		/**
		 * if we have no module provided we must check if we have setup a start page within system configuration
		 * if so we need to override the module, action and additional params, first we check if we have an alias
		 * if not we just parse the url string
		 */
		if (empty($params->module)) {
			$start_page = $this->core->dbconfig("system", system::CONFIG_DEFAULT_PAGE);
			if (!empty($start_page)) {
				$start_page_params = UrlAliasObj::get_params_from_alias($start_page);
				if ($start_page_params === false) {
					$start_page_params = UrlAliasObj::parse_url_string($start_page);
				}
				else {
					$start_page_params['type'] = "";
				}
				$params->type = $start_page_params['type'];
				$this->action_params['module'] = $start_page_params['module'];
				$params->action = $start_page_params['action'];
				$additional_function_params = $start_page_params['additional_function_params'];
			}
		}

		// Parse additional function params.
		if (!empty($additional_function_params)) {

			$lastindex = count($additional_function_params) - 1;
			// Check if the request uri ends with a slash, if so we should remove the last array element if it is empty.
			if (preg_match("/\/$/", NetTools::get_request_uri())) {
				if (empty($additional_function_params[$lastindex])) {
					unset($additional_function_params[$lastindex]);
					$lastindex--;
				}
			}

			// Next we have a none empty last element, so check for common file endings and remove it.
			// Last parameter will be without the extension.
			if (!empty($additional_function_params)) {
				if (preg_match("/(.*)\.(ajax|ajax_html|html)?$/is", $additional_function_params[$lastindex], $matches)) {
					$additional_function_params[$lastindex] = $matches[1];
				}
			}
		}

		$this->action_params = $params->get_values();
		$this->action_params['action_params'] = $additional_function_params;
	}

	/**
	 * Check for login.
	 */
	private function check_login() {
		/**
		 * @TODO check if the !empty db is enough
		 */
		#if((!empty($this->core->db) && ($this->action_params['module'] == 'system' && $this->core->dbconfig("system", "installed") == "1") || $this->action_params['module'] != 'system')) {
		if (!empty($this->core->db)) {
			$this->core->get_session()->check_login();
		}
	}

	/**
	 * Switch on action type.
	 */
	private function switch_action_type() {
		switch ($this->action_params['type']) {
			case 'ajax_request':
				$this->request_ajax();
				break;
			case 'ajax_html':
				$this->core->init_type = Core::INIT_TYPE_AJAXHTML;
			default:
				$this->request_normal();
				break;
		}
	}

	/**
	 * Request an ajax call.
	 */
	private function request_ajax() {
		$module = $this->action_params['module'];
		$mod = "modules/" . $this->action_params['module'];
		if (!empty($this->action_params['ajax_module'])) {
			$module = $this->action_params['ajax_module'];
			$mod = "modules/" . $this->action_params['ajax_module'];
		}
		if (!empty($this->db)) {
			$module_conf_obj = new ModulConfigObj($module);
			if ((!$module_conf_obj->load_success() || $module_conf_obj->enabled != 1) && $module != "system") {
				AjaxModul::return_code(AjaxModul::ERROR_MODULE_NOT_FOUND);
			}

			if (!empty($this->action_params['perm']) && !$this->core->get_right_manager()->has_perm($this->action_params['perm'])) {
				AjaxModul::return_code(AjaxModul::ERROR_NO_RIGHTS);
			}
		}
		$ajax_file = SITEPATH . "/" . $mod . "/ajax/" . $this->action_params['action'] . ".php";
		if (file_exists($ajax_file)) {
			$arr = explode("_", $this->action_params['action']);
			foreach ($arr AS &$val) {
				$val = ucfirst($val);
			}
			$class = 'Ajax' . ucfirst($this->action_params['module']) . implode("", $arr);

			if (!class_exists($class)) {
				include($ajax_file);
			}

			$ajax_run = new $class();
			$ajax_run->run();
		}
	}

	/**
	 * Request a normal page.
	 */
	private function request_normal() {
		$used_default_module = false;
		$original_module = $this->action_params['module'];
		if (!file_exists(SITEPATH . "/modules/" . $this->action_params['module'] . "/" . $this->action_params['module'] . ".php")) {
			$this->action_params['module'] = $this->core->core_config("core", "default_module");
			$used_default_module = true;
		}

		if ($this->core->lng) {
			$this->core->lng->load("intl");
			$this->core->lng->load("menu");
			$this->core->lng->load($this->action_params['module']);
			$this->core->lng->load_javascript($this->action_params['module']);
		}
		$modulname = $this->action_params['module'];
		$module_conf_obj = new ModulConfigObj($modulname);
		if ((!$module_conf_obj->load_success() || $module_conf_obj->enabled != 1) && $modulname != "system") {
			$this->assign_default_js_css();
			$this->core->message(t("Module not found or disabled"), Core::MESSAGE_TYPE_ERROR);
			$this->smarty->assign("module_tpl", "");
			$this->aborting_loading();
		}
		$actions_path = SITEPATH . '/modules/' . $modulname . '/actions';
		if ($used_default_module && (file_exists($actions_path . '/' . $original_module . '.php') || method_exists($modulname, $original_module))) {

			if (!empty($this->action_params['action'])) {
				array_unshift($this->action_params['action_params'], $this->action_params['action']);
			}
			$this->action_params['action'] = $original_module;
		}
		$action = $this->action_params['action'];

		if (file_exists($actions_path . '/' . $action . '.php')) {
			$load_class = $modulname . "_" . $action;
		}
		else {
			$load_class = $modulname;
		}

		/* @var $module ActionModul */
		$module = new $load_class();
		$module->modulname = $this->action_params['module'];
		$module->action = $action;
		$module->additional_params = $this->action_params['action_params'];

		$module->__init();

		if (!empty($this->action_params['perm']) && !$this->core->get_right_manager()->has_perm($this->action_params['perm'])) {
			$this->assign_default_js_css();
			$module->no_permission();
			$this->aborting_loading();
		}

		$this->core->cache("core", "current_module", $module->modulname);

		$parent = get_parent_class($modulname);
		if ($parent != "ActionModul" && get_parent_class($parent) != "ActionModul") {
			$this->assign_default_js_css();
			$module->wrong_params();
			$this->aborting_loading();
		}

		if ($module->action == ActionModul::NO_DEFAULT_METHOD || !method_exists($module, $module->action)) {
			$this->assign_default_js_css();
			$module->clear_output();
			$this->aborting_loading();
		}

		// Prevent direct calling a hook method.
		if (preg_match("/^hook_/", $module->action)) {
			$this->assign_default_js_css();
			$module->wrong_params();
			$this->aborting_loading();
		}

		//Get the calling class method
		$method = new ReflectionMethod($module, $module->action);

		//Check if we provided all required parameters, if not abort loading and display error message
		if ($method->getNumberOfRequiredParameters() > count($this->action_params['action_params'])) {
			$this->assign_default_js_css();
			$module->wrong_params();
			$this->aborting_loading();
		}

		//Call the wanted module action
		call_user_func_array(array($module, $action), $this->action_params['action_params']);

		$module_template = "";

		if (!empty($module->static_tpl)) {
			if ($module->static_tpl === NS) {
				$module->static_tpl = "";
			}
			$module_template = $module->static_tpl;
		}
		else {
			$module_template = SITEPATH . "/modules/" . $module->modulname . "/templates/" . $module->module_tpl;
		}

		if (!empty($module_template) && $this->smarty->templateExists($module_template)) {
			$this->smarty->assign("module_tpl", $module_template);
		}
		else if (!empty($module_template)) {
			$this->smarty->assign("module_tpl_old", $module_template);
			$this->smarty->assign("module_tpl", "template_not_exists.tpl");
		}

		if (!empty($this->action_params['popup'])) {
			$this->smarty->assign("popup", "1");
		}

		if (!empty($this->action_params['dialog'])) {
			$this->smarty->assign("dialog", "1");
		}

		switch ($this->action_params['type']) {
			case 'ajax_html':
				$this->core->js_config("is_ajax_html", true);
				$this->core->template = "index_ajax_html.tpl";
				break;
			default:
				$this->assign_default_js_css();
				$this->core->assign_menus();
				break;
		}
		$module->assign_default();
		$this->core->smarty_assign_default_vars();
		$this->smarty->display($this->core->template);
	}

	/**
	 * Abort loading.
	 */
	private function aborting_loading() {
		$this->core->assign_menus();
		$this->core->smarty_assign_default_vars();
		$this->core->smarty->display($this->core->template);
		display_xhprof_run();
		die();
	}

	/**
	 * Assign default js and css files.
	 */
	private function assign_default_js_css() {

		//Define default css files
		$this->core->add_css("/css/master.css");
		$dir = new Dir('/css/jquery_soopfw', false);
		$dir->just_files();
		$dir->file_extension('css');
		$dir->file_regexp('.*jquery-ui-[0-9.]+.*');

		$jquery_ui_css_versions = array();
		$jquery_ui_js_version = "";

		foreach ($dir->search() AS $file_entry) {
			if (preg_match("/jquery-ui-([0-9]+\.[0-9]+\.[0-9]+).*/", $file_entry->filename, $matches)) {
				$jquery_ui_css_versions[$matches[1]] = $file_entry->path;
			}
		}
		krsort($jquery_ui_css_versions);
		foreach ($jquery_ui_css_versions AS $version => $file) {
			$jquery_ui_js_version = '/js/jquery_plugins/jquery-ui-' . $version . '.custom.min.js';
			if (file_exists(SITEPATH . $jquery_ui_js_version)) {
				$this->core->add_css(str_replace(SITEPATH, '', $file));
				break;
			}
			elseif (file_exists(SITEPATH . $this->core->smarty->get_tpl() . $jquery_ui_js_version)) {
				$this->core->add_css(str_replace(SITEPATH, '', $file));
				break;
			}
			else {
				$jquery_ui_js_version = "";
			}
		}

		$this->core->add_css("/css/jquery_soopfw/jquery-ui-datetime-picker.css");
		$this->core->add_css("/css/jquery_soopfw/jquery.qtip.css");
		$this->core->add_css("/css/jquery_soopfw/jquery.sceditor.default.min.css");
		$this->core->add_css("/css/jquery_overrides.css");

		$this->core->add_css("/css/soopfw_icons.css");

		$this->core->add_css("/css/fileuploader.css");

		$this->core->add_css("/css/admin_menu.css");
		$this->core->add_css("/css/menu.css");
		$this->core->add_css("/css/box.css");
		$this->core->add_css("/css/form.css");
		$this->core->add_css("/css/popup.css");
		$this->core->add_css("/css/table.css");
		$this->core->add_css("/css/pager.css");

		$this->core->add_css($this->core->smarty->get_tpl() . "/css/styles.css");



		//Add default javascript files
		$this->core->add_js("/js/jquery-1.7.2.min.js", Core::JS_SCOPE_SYSTEM);
		if (!empty($jquery_ui_js_version)) {
			$this->core->add_js($jquery_ui_js_version, Core::JS_SCOPE_SYSTEM);
		}
		$this->core->add_js("/js/jquery_plugins/jquery.ui.i18n.all.min.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.ui.droppable.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery-ui-timepicker-addon.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.maskedinput-1.3.min.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.validator-0.3.3.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.ajaxQueue.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.qtip.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.tablednd.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.metadata.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.tablesorter.min.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.ui.tabs.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.sceditor.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.sceditor.bbcode.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery-fieldselection.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/jquery_plugins/jquery.endless-scroll.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/adminmenu.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/common.js", Core::JS_SCOPE_SYSTEM);
		$this->core->add_js("/js/core.js", Core::JS_SCOPE_SYSTEM);

		$this->core->add_js("/js/fileuploader.js", Core::JS_SCOPE_USER);
		$this->core->add_js("/js/SoopfwPager.js", Core::JS_SCOPE_USER);
		if (file_exists(SITEPATH . $this->core->smarty->get_tpl() . '/js/main.js')) {
			$this->core->add_js($this->core->smarty->get_tpl() . '/js/main.js', Core::JS_SCOPE_USER);
		}
	}

}