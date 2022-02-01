<?php

class CleanTalk_ControllerPublic_CleanTalkThread extends XFCP_CleanTalk_ControllerPublic_CleanTalkThread {

    public function actionReply() {
        $options = XenForo_Application::getOptions();
	if ($options->get('cleantalk', 'enabled_comm')) {
            $field_name = CleanTalk_Base_CleanTalk::getCheckjsName();
            $ct_check = CleanTalk_Base_CleanTalk::getCheckjsValue();
            setcookie($field_name, $ct_check, 0, '/; samesite=Lax');
        }
	return parent::actionReply();
    }

}
