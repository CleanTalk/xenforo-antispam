<?php
class CleanTalk_Base_CleanTalk {
		
    static public function getCheckjsName() {
		return 'ct_checkjs';
    }
    
	/* Queries for install/uninstall hooks */
	protected static $queries = array(
		'extendUserTable' => '
			ALTER TABLE `xf_user`           
			ADD COLUMN `ct_check` VARCHAR(35) NULL AFTER `is_staff`;',
		'SlashUserTable' => '
			ALTER TABLE `xf_user`
			DROP COLUMN `ct_check`;',
		'upgradeUserTable' =>'
			SHOW COLUMNS FROM `xf_user` LIKE "ct_check";' 
	);
	
	/* Insatll Hook */
	public static function installHook(){
		$db = XenForo_Application::get('db');
		if (count($db->fetchAll(self::$queries['upgradeUserTable'])) === 0)
			$db->query(self::$queries['extendUserTable']);
	}
	
	/* Unnistall Hook */
	public static function uninstallHook(){
		$db = XenForo_Application::get('db');
		$db->query(self::$queries['SlashUserTable']);
	}
	
	public static function CheckUsersOutput($content, $params, $template){
						
		$ret_val = "";	
		
		// If access key is unset
		$options = XenForo_Application::getOptions();
		$api_key = $options->get('cleantalk', 'apikey');
		if(empty($api_key))
			$ret_val .= "<h1 style='margin: 20px; text-align: center;'>Acess key is empty.</h1>";
				
		/*	Check button */
		$ret_val .= '
			<dl class="ctrlUnit">
			<div style="text-align: center;">
				<input type="submit" class="button primary" name="cleantalk_check_spam_users" value="Check for spam-users" />
			</div>
				<dt></dt>
				<dd>
					<ul>
						<input type="hidden" name="options[cleantalk_check_users_option][test]" value="sdt" id="ctrl_optionsccleantalk_check_userslink_1">
						<input type="hidden" name="options_listed[]" value="cleantalk_check_users_option">
					</ul>
				</dd>
			</dl>
			<script>
				$(function(){
					$(".submitUnit").css("display", "none");
				});
				
			</script>
		';
		
		/* Showing all found spam users */
		$start_entry = '0';		
		if(isset($_GET['start_entry']) && intval($_GET['start_entry']))
			$start_entry = strval(intval($_GET['start_entry']));
		
		$on_page = '20';
		$end_entry = strval(intval($start_entry) + intval($on_page));
				
		$db = XenForo_Application::get('db');
		
		/*Count spam users */
		$spam_users_count = $db->fetchAll("
			SELECT
				COUNT(user_id) AS cnt
			FROM xf_user
			WHERE ct_check = 'spam'
		");
		$spam_users_count = $spam_users_count[0]['cnt'];
		
		/* Get spam users */
		$spam_users = $db->fetchAll("
			SELECT
				user.user_id AS id,
				user.username AS username,
				user.register_date AS register,
				user.last_activity AS activity,
				user.email AS email,
				user.message_count
			FROM xf_user user
			WHERE ct_check = 'spam'
			LIMIT $start_entry, $end_entry;
		");
		
		if(count($spam_users)){
			$ret_val .= "<table style='width: 100%;'>";
			$ret_val .= "
				<tr style='width: 100%; height: 40px; border-bottom: 2px rgba(23,96,147,1) solid;'>
				<td><input style='opacity: 0' type='checkbox'></td>
				<td>ID</td>
				<td>Username</td>
				<td>Email</td>
				<td>Registred</td>
				<td>Last Activity</td>
				<td>Posts</td>
				</tr>
			";
		
			foreach($spam_users as $key => $value){
				$ret_val .= "
					<tr style='width: 100%; height: 40px;'>
						<td><input name='users_for_deleting[]' type='checkbox' value='{$value['id']}'></td>
						<td>{$value['id']}</td>
						<td>{$value['username']}</td>
						<td>{$value['email']}</td>
						<td>".date('Y-m-d H:i:s', $value['register'])."</td>
						<td>".date('Y-m-d H:i:s', $value['activity'])."</td>
						<td>{$value['message_count']}</td>
						</td>
					</tr>
				";
			}unset($key, $value);
			
			$ret_val .= '</table><br>';
			
			$pages = ceil(intval($spam_users_count) / $on_page);
			
			if($pages > 1){
				$ret_val .= "<ul><li style='display: inline-block; margin: 10px 5px;'>Pages:</li>";
				for($i=1; $pages >= $i; $i++){
						$ret_val .= "					
							<li style='display: inline-block; padding: 3px 5px; background: rgba(23,96,147,".((isset($_GET['curr_page']) && $_GET['curr_page'] == $i) || (!isset($_GET['curr_page']) && $i == 1) ? "0.6" : "0.3")."); border-radius: 3px;'>
								<a href='admin.php?options/list/cleantalk_check_uesrs&start_entry=".($i-1)*$on_page."&curr_page=$i'>$i</a>
							</li>";
				}
				$ret_val .= "</ul>";
			}
			
			$ret_val .= '
			<div style="text-align: center;">
				<input type="submit" class="button primary" name="cleantalk_delete_spam_users" value="Delete selected spam-users" />
				<input type="submit" class="button primary" name="cleantalk_delete_all_spam_users" value="Delete ALL spam-users" />
				<br>
				<h3 style="margin-top: 10px;">All user\'s post will be also deleted.</h3>
			</div>
			';
				
		}else
			$ret_val .= '<h2 style="text-align:center">No spam-users were found.</h2>';
				
		return $ret_val;
		
	}
	
	static function CheckUsersCallback($settings, $abc){
				
		$options = XenForo_Application::getOptions();
		
		if(isset($_POST['cleantalk_check_spam_users']) && $_POST['cleantalk_check_spam_users']){
			
			$db = XenForo_Application::get('db');
			
			$result = $db->fetchAll("
				SELECT
					DISTINCT(user.user_id) AS id,
					user.email AS email,
					ips.ip as ip
				FROM xf_user user
					INNER JOIN xf_ip ips
					ON user.user_id = ips.user_id;
			");
			
			$users_data = array();
			$data_to_send = array();
			foreach($result as $key => $value){
				$ip = unpack("Nip", $value['ip']);
				$ip = long2ip($ip['ip']);
				//*$users_data[$value['id']] = array('email' => $value['email'], 'ip' => $value['ip']);
				$users_data[$value['email']] = $value['id'];
				$users_data[$ip] = $value['id'];
				$data_to_send[] = $value['email'];
				$data_to_send[] = $ip;
			}
			$data_to_send = implode(',',$data_to_send);
						
			/*	Sending the request */
			$request = Array();
			$request['method_name'] = 'spam_check_cms';
			$request['auth_key'] = $options->get('cleantalk', 'apikey');
			$request['data'] = $data_to_send;
			$url='https://api.cleantalk.org';
			
			if(!function_exists('sendRawRequest'))
				require_once('cleantalk.class.php');
			$result = sendRawRequest($url, $request, false, 5);
			$result = json_decode($result, true);
			
			if(isset($result['error_message'])){
				error_log('CleanTalk plugin -> Check users -> Server returns error: '.$result['error_message']);
				return true;
			}else{
				$spam_users = array();
				$sql_append = '';
				foreach($result['data'] as $key => $value){
					if($value['appears'] == 1){
						if(array_key_exists($key, $users_data)){
							$spam_users[] = $users_data[$key];
						}
					}
				}
			}
			
			if(count($spam_users)){
				$sql = "
					UPDATE xf_user user
					SET user.ct_check = 'spam'
					WHERE user.user_id = ".$spam_users[0];

				for($i=1; isset($spam_users[$i]); $i++)
					$sql .= " OR user.user_id = ".$spam_users[$i]." ";
								
				$result = $db->query($sql);
			}			
		}
		
		if(isset($_POST['cleantalk_delete_spam_users']) && $_POST['cleantalk_delete_spam_users']){

			if(empty($_POST['users_for_deleting'])){
				return true;
			}
		
			$users_for_deleting = $_POST['users_for_deleting'];
			
			$db = XenForo_Application::get('db');
			$sql = "
				DELETE 
				FROM xf_user
				WHERE user_id = ".$users_for_deleting[0];
			
			for($i=1; isset($users_for_deleting[$i]); $i++)
				$sql .= " OR user_id = ".$users_for_deleting[$i]." ";
			
			$result = $db->query($sql);
			
			$sql = "
				DELETE 
				FROM xf_post
				WHERE user_id = ".$users_for_deleting[0];
			
			for($i=1; isset($users_for_deleting[$i]); $i++)
				$sql .= " OR user_id = ".$users_for_deleting[$i]." ";
			
			$result = $db->query($sql);
		}
		
		if(isset($_POST['cleantalk_delete_all_spam_users']) && $_POST['cleantalk_delete_all_spam_users']){
			
			$db = XenForo_Application::get('db');
			$sql = "
				DELETE 
					xf_user,
					xf_post
				FROM 
					xf_user,
					xf_post
				WHERE
					xf_user.ct_check = 'spam'
					AND 
					xf_user.user_id = xf_post.user_id";
			$result = $db->query($sql);
			
			$sql = "
				DELETE 
				FROM xf_user
				WHERE ct_check = 'spam'";
			$result = $db->query($sql);
			
		}
		
		return true;
		
	}
	
    public static function hookAdminSettings(XenForo_Visitor &$visitor ){
								
		if (
                        sizeof($_POST) > 0 &&
                        isset($_POST['options']) &&
                        isset($_POST['options']['cleantalk']) &&
                        isset($_POST['options']['cleantalk']['apikey']) &&
                        !empty($_POST['options']['cleantalk']['apikey'])
                )
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

			$ct_request = new CleantalkRequest();
			$ct_request->auth_key = $_POST['options']['cleantalk']['apikey'];
        		$ct_request->feedback = '0:xenforo-23';
        		$ct->sendFeedback($ct_request);
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
	var d = new Date(), 
		ctTimeMs = new Date().getTime(),
		ctMouseEventTimerFlag = true, //Reading interval flag
		ctMouseData = "[",
		ctMouseDataCounter = 0;
	
	function ctSetCookie(c_name, value) {
		document.cookie = c_name + "=" + encodeURIComponent(value) + "; path=/";
	}
	
	ctSetCookie("ct_ps_timestamp", Math.floor(new Date().getTime()/1000));
	ctSetCookie("ct_fkp_timestamp", "0");
	ctSetCookie("ct_pointer_data", "0");
	ctSetCookie("ct_timezone", "0");
	
	setTimeout(function(){
		ctSetCookie("ct_timezone", d.getTimezoneOffset()/60*(-1));
	},1000);
	
	//Reading interval
	var ctMouseReadInterval = setInterval(function(){
			ctMouseEventTimerFlag = true;
		}, 150);
		
	//Writting interval
	var ctMouseWriteDataInterval = setInterval(function(){
			var ctMouseDataToSend = ctMouseData.slice(0,-1).concat("]");
			ctSetCookie("ct_pointer_data", ctMouseDataToSend);
		}, 1200);
	
	//Stop observing function
	function ctMouseStopData(){
		if(typeof window.addEventListener == "function")
			window.removeEventListener("mousemove", ctFunctionMouseMove);
		else
			window.detachEvent("onmousemove", ctFunctionMouseMove);
		clearInterval(ctMouseReadInterval);
		clearInterval(ctMouseWriteDataInterval);				
	}
	
	//Logging mouse position each 300 ms
	var ctFunctionMouseMove = function output(event){
		if(ctMouseEventTimerFlag == true){
			var mouseDate = new Date();
			ctMouseData += "[" + Math.round(event.pageY) + "," + Math.round(event.pageX) + "," + Math.round(mouseDate.getTime() - ctTimeMs) + "],";
			ctMouseDataCounter++;
			ctMouseEventTimerFlag = false;
			if(ctMouseDataCounter >= 100)
				ctMouseStopData();
		}
	}
	
	//Stop key listening function
	function ctKeyStopStopListening(){
		if(typeof window.addEventListener == "function"){
			window.removeEventListener("mousedown", ctFunctionFirstKey);
			window.removeEventListener("keydown", ctFunctionFirstKey);
		}else{
			window.detachEvent("mousedown", ctFunctionFirstKey);
			window.detachEvent("keydown", ctFunctionFirstKey);
		}
	}
	
	//Writing first key press timestamp
	var ctFunctionFirstKey = function output(event){
		var KeyTimestamp = Math.floor(new Date().getTime()/1000);
		ctSetCookie("ct_fkp_timestamp", KeyTimestamp);
		ctKeyStopStopListening();
	}

	if(typeof window.addEventListener == "function"){
		window.addEventListener("mousemove", ctFunctionMouseMove);
		window.addEventListener("mousedown", ctFunctionFirstKey);
		window.addEventListener("keydown", ctFunctionFirstKey);
	}else{
		window.attachEvent("onmousemove", ctFunctionMouseMove);
		window.attachEvent("mousedown", ctFunctionFirstKey);
		window.attachEvent("keydown", ctFunctionFirstKey);
	}
</script>';
	    $ret_val = sprintf($js_template, $field_name, $ct_check_value);
	    if($options->get('cleantalk', 'link'))
	    {
	    	$ret_val.="<div style='width:100%;text-align:center'><a href='https://cleantalk.org/xenforo-antispam-addon'>XenForo spam</a> blocked by CleanTalk.</div>";
	    }
	}
//	XenForo_Application::getSession()->set('ct_submit_comment_time', time()); - got error 'The session has been saved and is now read-only'
//	XenForo_Application::setSimpleCacheData('ct_submit_comment_time', time());
	return $ret_val;
    }

}
