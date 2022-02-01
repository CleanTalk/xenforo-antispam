<?php

class CleanTalk_ControllerPublic_CleanTalkRegister extends XFCP_CleanTalk_ControllerPublic_CleanTalkRegister {

    protected function _getRegisterFormResponse(array $fields, array $errors = array()) {
        $options = XenForo_Application::getOptions();
	if ($options->get('cleantalk', 'enabled_reg')) {
            $field_name = CleanTalk_Base_CleanTalk::getCheckjsName();
            $ct_check = CleanTalk_Base_CleanTalk::getCheckjsValue();
            setcookie($field_name, $ct_check, 0, '/; samesite=Lax');
        }
	return parent::_getRegisterFormResponse($fields, $errors);
    }

}
