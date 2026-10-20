<?php
use UplinkPress\MediaBridgeForBricks\Folder_Import;
wp_set_current_user(1);if(!defined('DOING_AJAX'))define('DOING_AJAX',true);
class MBB_Import_Response extends RuntimeException{}
$die=static fn()=>static function($message=''){throw new MBB_Import_Response((string)$message);};add_filter('wp_die_ajax_handler',$die);
$before=wp_json_encode(get_option('uplink_mbb_happyfiles_import'));
try{
 foreach([['source'=>['unexpected']],['source'=>'../users'],['source'=>'filebird']]as$payload){
  $_POST=array_merge(['mode'=>'start','include_media'=>'1','select_provider'=>'1'],$payload);$_REQUEST=['nonce'=>wp_create_nonce('uplink_mbb_happyfiles_import')];ob_start();try{(new Folder_Import())->ajax();}catch(MBB_Import_Response $e){}$result=json_decode(ob_get_clean(),true);if(!isset($result['success'])||$result['success']!==false)throw new RuntimeException('Malformed source accepted');echo "PASS: Unsupported or malformed AJAX source rejected\n";
 }
 if($before!==wp_json_encode(get_option('uplink_mbb_happyfiles_import')))throw new RuntimeException('Rejected request changed job');echo "PASS: Rejected source requests leave job unchanged\n";
 if(!has_action('wp_ajax_uplink_mbb_folder_import')||!has_action('wp_ajax_uplink_mbb_happyfiles_import'))throw new RuntimeException('Missing route');echo "PASS: New route and beta.7 compatibility route registered\n";
}finally{remove_filter('wp_die_ajax_handler',$die);}
