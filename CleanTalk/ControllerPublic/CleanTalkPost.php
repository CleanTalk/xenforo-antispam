<?php

class CleanTalk_ControllerPublic_CleanTalkPost extends XFCP_CleanTalk_ControllerPublic_CleanTalkPost {

    public function actionEdit() {
        $options = XenForo_Application::getOptions();
	if ($options->get('cleantalk', 'enabled_comm')) {
            XenForo_Application::getSession()->set('ct_submit_comment_time', time());
            $field_name = CleanTalk_Base_CleanTalk::getCheckjsName();
            $ct_check_def = CleanTalk_Base_CleanTalk::getCheckjsDefaultValue();
            setcookie($field_name, $ct_check_def, 0, '/');
        }
	return parent::actionEdit();
    }

}
