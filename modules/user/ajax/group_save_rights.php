<?php
/**
 * Save the new rights for the group
 */
//Check params
if (!$core->get_right_manager()->has_perm("admin.user.group.change")) {
	AjaxModul::return_code(AjaxModul::ERROR_NO_RIGHTS, null, true);
}

//Setup needed params
$params = new ParamStruct();
$params->add_required_param("group_id", PDT_INT);
$params->add_isset_param("rights", PDT_ARR);
$params->fill();

//Check params
if (!$params->is_valid()) {
	AjaxModul::return_code(AjaxModul::ERROR_MISSING_PARAMETER, null, true);
}

//Get the new rights string
$new_rights = implode("\n", $params->rights);

//Load the group, set the value and save it
$right_obj = new UserRightGroupObj($params->group_id);
$right_obj->permissions = $new_rights;
if ($right_obj->save()) {
	
	/**
	 * Provides hook: group_save_rights
	 *  
	 * Allow other modules to do tasks if the rights changed for the specific group
	 * 
	 * @param int $group_id
	 *   The group id
	 * @param array $permissions
	 *   the current permissions for the group (includes the changes) 
	 */
	$core->hook('group_save_rights', array($params->group_id, $params->rights));
	AjaxModul::return_code(AjaxModul::SUCCESS, null, true);
}
AjaxModul::return_code(AjaxModul::ERROR_DEFAULT, null, true);
?>
