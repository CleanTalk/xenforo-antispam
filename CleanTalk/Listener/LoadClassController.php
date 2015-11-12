<?php
class CleanTalk_Listener_LoadClassController {

        public static function loadClassListener($class, &$extend) {
                if ($class == 'XenForo_ControllerPublic_Register') {
                        $extend[] = 'CleanTalk_ControllerPublic_CleanTalk';
                }
        }

}
