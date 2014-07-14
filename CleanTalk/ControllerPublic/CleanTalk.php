<?php

class CleanTalk_ControllerPublic_CleanTalk extends XFCP_CleanTalk_ControllerPublic_CleanTalk {

    protected function _getRegisterFormResponse(array $fields, array $errors = array()) {
	XenForo_Application::getSession()->set('ct_submit_register_time', time());
	$field_name = CleanTalk_Base_CleanTalk::getCheckjsName();
	$ct_check_def = CleanTalk_Base_CleanTalk::getCheckjsDefaultValue();
	//if (!isset($_COOKIE[$field_name])) {
	    setcookie($field_name, $ct_check_def, 0, '/');
	//}
	return parent::_getRegisterFormResponse($fields, $errors);
    }

}
