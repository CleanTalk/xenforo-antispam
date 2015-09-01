<?php

class CleanTalk_Base_CleanTalk {

    static public function getCheckjsName() {
	return 'ct_checkjs';
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
	}
	return $ret_val;
    }

}
