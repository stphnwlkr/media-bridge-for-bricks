<?php
use UplinkPress\MediaBridgeForBricks\Folder_Cleanup;
wp_set_current_user(1);if(!defined('DOING_AJAX'))define('DOING_AJAX',true);
class MBB_Cleanup_Response extends RuntimeException{}
$die=static fn()=>static function($message=''){throw new MBB_Cleanup_Response((string)$message);};add_filter('wp_die_ajax_handler',$die);
function cq_request($data,$nonce){$_POST=$data;$_REQUEST=['nonce'=>$nonce];ob_start();$msg='';try{(new Folder_Cleanup())->ajax();}catch(MBB_Cleanup_Response $e){$msg=$e->getMessage();}$out=ob_get_clean();return $out?:$msg;}
function cq_check($ok,$s){if(!$ok)throw new RuntimeException($s);echo "PASS: $s\n";}
$before=wp_json_encode([get_option('uplink_mbb_folder_cleanup'),get_option('active_plugins'),get_option('uplink_mbb_import_receipts')]);
try{
 cq_check(cq_request(['mode'=>'start','source'=>'happyfiles','confirmed'=>'1'],'invalid')==='-1','Cleanup rejects an invalid CSRF nonce');
 foreach([['mode'=>'preview','source'=>'../users'],['mode'=>'start','source'=>['invalid'],'confirmed'=>'1'],['mode'=>'start','source'=>'wicked-folders','confirmed'=>'0'],['mode'=>'retire','source'=>'happyfiles','job'=>'unauthorized'],['mode'=>'step','job'=>'not-a-job']]as$payload){$r=json_decode(cq_request($payload,wp_create_nonce('uplink_mbb_folder_cleanup')),true);cq_check(isset($r['success'])&&!$r['success'],'Invalid or unconfirmed cleanup request rejected');}
 wp_set_current_user(0);$r=json_decode(cq_request(['mode'=>'preview','source'=>'happyfiles'],wp_create_nonce('uplink_mbb_folder_cleanup')),true);cq_check(isset($r['success'])&&!$r['success'],'Anonymous cleanup request rejected');wp_set_current_user(1);
 cq_check($before===wp_json_encode([get_option('uplink_mbb_folder_cleanup'),get_option('active_plugins'),get_option('uplink_mbb_import_receipts')]),'Rejected requests preserve jobs, receipts and plugin activation');
}finally{wp_set_current_user(1);remove_filter('wp_die_ajax_handler',$die);}
