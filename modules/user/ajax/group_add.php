<?php
/**
 * Provides an ajax request to add a right group.
 *
 * @copyright Christian Ackermann (c) 2010 - End of life
 * @author Christian Ackermann <prdatur@gmail.com>
 * @package modules.user.ajax
 * @category Module.User
 */
class AjaxUserGroupAdd extends AjaxModul {

	/**
	 * This function will be executed after ajax file initializing
	 */
	public function run() {
		//Check perms
		if (!$this->core->get_right_manager()->has_perm("admin.user.group.add")) {
			AjaxModul::return_code(AjaxModul::ERROR_NO_RIGHTS);
		}

		//Setup params
		$params = new ParamStruct();
		$params->add_required_param("title", PDT_STRING);

		$params->fill();

		//Check if params valid
		if (!$params->is_valid()) {
			AjaxModul::return_code(AjaxModul::ERROR_MISSING_PARAMETER);
		}

		//Insert the group and return the created group id
		$group_obj = new UserRightGroupObj();
		$group_obj->set_fields($params->get_values());
		if ($group_obj->insert()) {
			$return_array['group_id'] = $group_obj->get_last_inserted_id();
			AjaxModul::return_code(AjaxModul::SUCCESS, $return_array, true);
		}
		AjaxModul::return_code(AjaxModul::ERROR_DEFAULT);
	}
}
?>
