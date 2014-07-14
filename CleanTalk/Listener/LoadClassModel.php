<?php

class CleanTalk_Listener_LoadClassModel {

        public static function loadClassListener($class, &$extend) {
                if ($class == 'XenForo_Model_SpamPrevention') {
                        $extend[] = 'CleanTalk_Model_CleanTalk';
                }
        }

}
