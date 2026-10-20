<?php
/** Disposable local-site integration fixtures; leaves HappyFiles source and existing Media Bridge data intact. */
use UplinkPress\MediaBridgeForBricks\HappyFiles_Import as Import;
use UplinkPress\MediaBridgeForBricks\Plugin;
use UplinkPress\MediaBridgeForBricks\Image_Metadata;
use UplinkPress\MediaBridgeForBricks\Native_Media_Library;
use UplinkPress\MediaBridgeForBricks\Folder_Provider;
use UplinkPress\MediaBridgeForBricks\Bricks_Folder_Provider;
wp_set_current_user(1);
if (!defined('DOING_AJAX')) define('DOING_AJAX',true);
class MBB_Audit_Die extends RuntimeException {}
$die=static fn()=>static function($message=''){throw new MBB_Audit_Die((string)$message);};add_filter('wp_die_ajax_handler',$die);
function audit_assert($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function audit_ajax($fn,$data,$nonce){$_POST=$data;$_REQUEST=array('nonce'=>$nonce);ob_start();$died='';try{$fn();}catch(MBB_Audit_Die $e){$died=$e->getMessage();}$out=ob_get_clean();return $out!==''?$out:$died;}
function run_import(){ $j=Import::start(true,false);audit_assert(!is_wp_error($j),'Administrator starts import');$first=Import::step($j['id']);audit_assert($first['cursor']<=30,'Folder batch is bounded');$j=$first;for($i=0;$i<30&&$j['status']==='running';$i++)$j=Import::step($j['id']);audit_assert($j['status']==='complete','Batched import completes');return $j; }
$settings=get_option('uplink_mbb_settings');$savedjob=get_option('uplink_mbb_happyfiles_import');$oldtargets=get_terms(array('taxonomy'=>'mbb_folder','hide_empty'=>false,'fields'=>'ids'));$hf=[];$images=[];$users=[];$parents=[];$source_snapshot=get_terms(array('taxonomy'=>'happyfiles_category','hide_empty'=>false));$tag='MBB audit '.wp_generate_password(8,false);
try {
 for($i=0;$i<35;$i++){$r=wp_insert_term($tag.' '.($i===2?'" <b>safe</b> & name':$i),'happyfiles_category',array('parent'=>$i===1?$hf[0]:0));$hf[]=(int)$r['term_id'];update_term_meta(end($hf),'happyfiles_position',35-$i);}
 for($i=0;$i<105;$i++){$id=wp_insert_attachment(array('post_title'=>$tag.' image '.$i,'post_author'=>1,'post_status'=>'inherit','post_mime_type'=>'image/jpeg'));$images[]=$id;wp_set_object_terms($id,array($hf[0],$hf[1]),'happyfiles_category');}
 $j=run_import();audit_assert($j['assigned']>=210,'All assignments copied across 100-item batches');
 $target=(int)get_terms(array('taxonomy'=>'mbb_folder','hide_empty'=>false,'meta_key'=>'_uplink_mbb_happyfiles_source','meta_value'=>(string)$hf[0],'fields'=>'ids'))[0];
 $child=(int)get_terms(array('taxonomy'=>'mbb_folder','hide_empty'=>false,'meta_key'=>'_uplink_mbb_happyfiles_source','meta_value'=>(string)$hf[1],'fields'=>'ids'))[0];
 audit_assert((int)get_term($child,'mbb_folder')->parent===$target,'Nested folder hierarchy copied');
 audit_assert(has_term($target,'mbb_folder',$images[104])&&has_term($child,'mbb_folder',$images[104]),'Last batch and multiple folder assignments copied');
 audit_assert(has_term($hf[0],'happyfiles_category',$images[104]),'HappyFiles assignments preserved');
 wp_update_term($target,'mbb_folder',array('name'=>$tag.' renamed'));
 $j=run_import();audit_assert($j['created']===0&&$j['assigned']===0,'Repeat import adds no duplicate folders or assignments');audit_assert(get_term($target,'mbb_folder')->name===$tag.' renamed','Repeat import preserves destination edits');
 $users[]=wp_insert_user(array('user_login'=>'mbb_author_'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(32),'role'=>'author'));
 $users[]=wp_insert_user(array('user_login'=>'mbb_admin_'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(32),'role'=>'administrator'));
 $owner=Import::start(false,false);wp_set_current_user($users[1]);audit_assert(is_wp_error(Import::step($owner['id']))&&is_wp_error(Import::start(true,false)),'Another administrator cannot take over an active job');wp_set_current_user(1);
 audit_assert(is_wp_error(Import::step('invalid-job')),'Invalid import session rejected');
 add_option('uplink_mbb_happyfiles_import_lock',time().':test','',false);audit_assert(is_wp_error(Import::step($owner['id'])),'Concurrent import request rejected');delete_option('uplink_mbb_happyfiles_import_lock');
 $private=wp_insert_post(array('post_status'=>'private','post_title'=>$tag.' private','post_author'=>1));$parents[]=$private;
 $password=wp_insert_post(array('post_status'=>'publish','post_title'=>$tag.' password','post_author'=>1,'post_password'=>'fixture-only'));$parents[]=$password;
 $secret=wp_insert_attachment(array('post_title'=>$tag.' secret','post_author'=>1,'post_status'=>'inherit','post_mime_type'=>'image/jpeg'),false,$private);$images[]=$secret;wp_update_attachment_metadata($secret,array('image_meta'=>array('camera'=>'PRIVATE SENTINEL')));
 $protected=wp_insert_attachment(array('post_title'=>$tag.' protected','post_author'=>1,'post_status'=>'inherit','post_mime_type'=>'image/jpeg'),false,$password);$images[]=$protected;
 $lib=new Native_Media_Library(new Folder_Provider(),false);$detail=new ReflectionMethod($lib,'attachment_data');$health=new ReflectionMethod($lib,'media_health_scan');
 audit_assert(Image_Metadata::attachment_exif($secret)['camera_model']==='PRIVATE SENTINEL','Administrator can read private metadata');$health->invoke($lib,true);
 wp_set_current_user($users[0]);
 audit_assert(!Import::can_import()&&is_wp_error(Import::start(true,true)),'Author cannot import or change provider');
 $provider=new Bricks_Folder_Provider();audit_assert(!$provider->can_manage()&&$provider->can_assign(),'Author can assign media but cannot manage shared folders');audit_assert(is_wp_error($provider->manage_folder(array('operation'=>'delete','folderId'=>$target))),'Author cannot delete shared folder');audit_assert(is_wp_error($provider->assign_attachment($images[0],$target)),'Author cannot move another author’s media');
 audit_assert([]===$detail->invoke($lib,$secret)&&[]===Image_Metadata::attachment_exif($secret),'Private attachment data and warmed EXIF cache denied');audit_assert(!in_array($secret,array_merge(...array_values($health->invoke($lib)['ids'])),true),'Shared health cache excludes unreadable media');
 audit_assert(!Image_Metadata::can_read($protected),'Password-protected parent respected');
 $out=audit_ajax(array($lib,'ajax_update_attachment'),array('attachment_id'=>$images[0],'title'=>'unauthorized'),wp_create_nonce('uplink_mbb_native_media'));audit_assert(empty(json_decode($out,true)['success']),'AJAX update denies another author’s attachment');
 wp_set_current_user(1);
 $out=audit_ajax(array(new Import(),'ajax'),array('mode'=>'start'),'invalid');audit_assert(trim($out)==='-1','Import rejects invalid CSRF nonce');
 $out=audit_ajax(array($lib,'ajax_save_collection'),array('name'=>'CSRF fixture'),'invalid');audit_assert(trim($out)==='-1','Folder mutation rejects invalid CSRF nonce');
 wp_set_current_user(0);audit_assert(is_wp_error(Import::start(true,true))&&!Image_Metadata::can_read($secret)&&!Image_Metadata::can_read($protected),'Anonymous access denied to import and protected metadata');
 audit_assert(Image_Metadata::can_read($images[0]),'Public unattached image remains readable');
 wp_set_current_user(1);
 audit_assert(wp_json_encode($source_snapshot)===wp_json_encode(get_terms(array('taxonomy'=>'happyfiles_category','hide_empty'=>false,'exclude'=>$hf))),'Existing HappyFiles source folders unchanged');
 echo "PASS: Security and HappyFiles integration fixtures complete\n";
} finally {
 wp_set_current_user(1);update_option('uplink_mbb_settings',$settings);if($savedjob===false)delete_option('uplink_mbb_happyfiles_import');else update_option('uplink_mbb_happyfiles_import',$savedjob,false);delete_option('uplink_mbb_happyfiles_import_lock');
 foreach($images as $id)wp_delete_attachment($id,true);foreach($parents as $id)wp_delete_post($id,true);
 foreach(array_reverse($hf) as $id)wp_delete_term($id,'happyfiles_category');
 $newtargets=get_terms(array('taxonomy'=>'mbb_folder','hide_empty'=>false,'fields'=>'ids'));foreach(array_reverse(array_diff($newtargets,$oldtargets)) as $id)wp_delete_term($id,'mbb_folder');
 require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $id)wp_delete_user($id);remove_filter('wp_die_ajax_handler',$die);delete_transient('uplink_mbb_media_health_v3');
}
