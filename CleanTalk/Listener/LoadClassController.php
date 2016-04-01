<?php
class CleanTalk_Listener_LoadClassController {

        public static function loadClassListenerRegister($class, &$extend) {
                if ($class == 'XenForo_ControllerPublic_Register') {
                        $extend[] = 'CleanTalk_ControllerPublic_CleanTalkRegister';
                }
        }

        public static function loadClassListenerPost($class, &$extend) {
                if ($class == 'XenForo_ControllerPublic_Post') {
                        $extend[] = 'CleanTalk_ControllerPublic_CleanTalkPost';
                }
        }

        public static function loadClassListenerForum($class, &$extend) {
                if ($class == 'XenForo_ControllerPublic_Forum') {
                        $extend[] = 'CleanTalk_ControllerPublic_CleanTalkForum';
                }
        }

        public static function loadClassListenerThread($class, &$extend) {
                if ($class == 'XenForo_ControllerPublic_Thread') {
                        $extend[] = 'CleanTalk_ControllerPublic_CleanTalkThread';
                }
        }

}
