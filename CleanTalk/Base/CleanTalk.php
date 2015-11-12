<?php
class CleanTalk_Base_CleanTalk {
    static public function getCheckjsName() {
	return 'ct_checkjs';
    }
    
    public static function hookAdminSettings(XenForo_Visitor &$visitor )
    {
    	$options = XenForo_Application::getOptions();
		if ($options->get('cleantalk', 'enabled') && sizeof($_POST)>0 && isset($_POST['options']) && isset($_POST['options']['cleantalk']))
		{
			require_once 'CleanTalk/Base/cleantalk.class.php';
			$ct_ws = array(
				'work_url' => 'http://moderate.cleantalk.org',
				'server_url' => 'http://moderate.cleantalk.org',
				'server_ttl' => 0,
				'server_changed' => 0
			    );
			$ct = new Cleantalk();
			$ct->work_url = $ct_ws['work_url'];
			$ct->server_url = $ct_ws['server_url'];
			$ct->server_ttl = $ct_ws['server_ttl'];
			$ct->server_changed = $ct_ws['server_changed'];
			
			$options = XenForo_Application::getOptions();
			
			$ct_request = new CleantalkRequest();
			$ct_request->auth_key = $_POST['options']['cleantalk']['apikey'];
			$ct_request->agent = 'xenforo-144';
			$ct_request->response_lang = 'en';
			$ct_request->js_on = 1;
			$ct_request->sender_email = "good@cleantalk.org";
			$ct_request->sender_nickname = "CleanTalk";
			$ct_request->sender_ip = $ct->ct_session_ip($_SERVER['REMOTE_ADDR']);
			$ct_request->submit_time = 0;
			$ct_request->message = "This message is a test to check the connection to the CleanTalk servers.";
			
			$ct_result = $ct->isAllowMessage($ct_request);
		}
    }
    
    /** Return Array of JS-keys for checking
	*
	* @return Array
	*/
	static public function getCheckJSArray() {
		$options = XenForo_Application::getOptions();
        $result=Array();
        for($i=-5;$i<=1;$i++) {
            $result[]=md5($options->get('cleantalk', 'apikey') . '+' . $options->get('contactEmailAddress') . date("Ymd",time()+86400*$i));
        }
        return $result;
	}

    static public function getCheckjsDefaultValue() {
	return '0';
    }

    static public function getCheckjsValue() {
	$options = XenForo_Application::getOptions();
	return md5($options->get('cleantalk', 'apikey') . '+' . $options->get('contactEmailAddress') . date("Ymd",time()));
    }

    public static function getTemplateAddon() {
	static $show_flag = TRUE;
	$ret_val = '';
	$options = XenForo_Application::getOptions();

	if ($show_flag) {
	    $show_flag = FALSE;
	    $field_name = self::getCheckjsName();
	    $ct_check_def = self::getCheckjsValue();
	    $ct_check_value = self::getCheckjsValue();
	    $js_template = '<script>
function ctSetCookie(c_name, value) {
  document.cookie = c_name + "=" + escape(value) + "; path=/";
}
ctSetCookie("%s", "%s");
</script>';
	    $ret_val = sprintf($js_template, $field_name, $ct_check_value);
	    if($options->get('cleantalk', 'link'))
	    {
	    	$ret_val.="<div style='width:100%;text-align:center'><a href='https://cleantalk.org/xenforo-antispam-addon'>XenForo spam</a> blocked by CleanTalk.</div>";
	    }
	}
	return $ret_val;
    }

}
