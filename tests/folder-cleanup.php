<?php
namespace UplinkPress\MediaBridgeForBricks {
 /** Simulate network activation within the single-site test installation. */
 function is_plugin_active_for_network($file){return in_array($file,$GLOBALS['mbb_mock_network']??[],true)||\is_plugin_active_for_network($file);}
}
namespace {
 use UplinkPress\MediaBridgeForBricks\Folder_Import as Import;
 use UplinkPress\MediaBridgeForBricks\Folder_Import_Sources as Sources;
 use UplinkPress\MediaBridgeForBricks\Folder_Cleanup as Cleanup;
 wp_set_current_user(1);require_once ABSPATH.'wp-admin/includes/plugin.php';
 function fc_check($ok,$message){if(!$ok)throw new \RuntimeException($message);echo "PASS: $message\n";}
 function fc_import($retire=true){$j=Import::start(true,false,'wicked-folders',$retire);if(is_wp_error($j))throw new \RuntimeException($j->get_error_message());for($i=0;$i<100&&$j['status']==='running';$i++)$j=Import::step($j['id']);fc_check($j['status']==='complete','Fixture import completed');return $j;}
 function fc_finish($j){for($i=0;$i<100&&$j['status']==='running';$i++){$next=Cleanup::step($j['id']);if(is_wp_error($next))throw new \RuntimeException($next->get_error_message());fc_check($next['assignments']-$j['assignments']<=100&&$next['removed']-$j['removed']<=30,'Cleanup respects relationship and folder batch limits');$j=$next;}fc_check($j['status']==='complete','Verified cleanup completed');return $j;}
 $options=['uplink_mbb_happyfiles_import','uplink_mbb_folder_cleanup','uplink_mbb_import_receipts','uplink_mbb_settings','active_plugins'];$saved=[];foreach($options as$n)$saved[$n]=get_option($n);$network=get_site_option('active_sitewide_plugins');$oldcache=wp_cache_get('plugins','plugins');$inventory=get_plugins();$terms=[];$images=[];$posts=[];$targets=get_terms(['taxonomy'=>'mbb_folder','hide_empty'=>false,'fields'=>'ids']);$tax='wf_attachment_folders';$registered=taxonomy_exists($tax);$tag='MBB cleanup '.wp_generate_uuid4();$users=[];$denier=null;
 try{
  $existing=Sources::terms('wicked-folders');fc_check(!$existing,'Cleanup tests require an empty disposable source taxonomy');
  foreach(Sources::definitions()as$key=>$def){
   $file=$key.'/mbb-status-fixture.php';$fake=$inventory;foreach(Sources::plugin_files($key)as$real)unset($fake[$real]);$fake[$file]=['Name'=>$key==='folders'?'Folders':($key==='wp-media-folder'?'WP Media Folder':$def['label'])];wp_cache_set('plugins',[''=>$fake],'plugins');
   update_option('active_plugins',array_merge($saved['active_plugins'],[$file]));fc_check(Sources::plugin_status($key)==='active',$def['label'].' active status detected');update_option('active_plugins',$saved['active_plugins']);fc_check(Sources::plugin_status($key)==='inactive',$def['label'].' installed inactive status detected');unset($fake[$file]);wp_cache_set('plugins',[''=>$fake],'plugins');fc_check(Sources::plugin_status($key)==='missing',$def['label'].' missing plugin status detected');
  }
  wp_cache_set('plugins',[''=>$inventory],'plugins');$fakefile='wicked-folders/mbb-cleanup-fixture.php';$fake=$inventory;$fake[$fakefile]=['Name'=>'Wicked Folders'];wp_cache_set('plugins',[''=>$fake],'plugins');
  if(!$registered)register_taxonomy($tax,'attachment',['hierarchical'=>true]);
  for($i=0;$i<35;$i++){$id=wp_insert_term($tag.' '.$i,$tax,['parent'=>$i===1?$terms[0]:0])['term_id'];$terms[]=$id;update_term_meta($id,'wf_order',$i);update_term_meta($id,'fixture_metadata','keep until cleanup');}
  for($i=0;$i<105;$i++){$id=wp_insert_attachment(['post_title'=>$tag.' image '.$i,'post_status'=>'inherit','post_mime_type'=>'image/jpeg','post_author'=>1]);$images[]=$id;wp_set_object_terms($id,[$terms[0],$terms[1]],$tax);update_post_meta($id,'_wicked_folder_order__wf_attachment_folders__'.$terms[0],$i);update_post_meta($id,'mbb_unrelated','preserve');}
  $j=fc_import();if(!$registered)unregister_taxonomy($tax);
  fc_check(is_wp_error(Cleanup::start('wicked-folders',false)),'Cleanup requires explicit confirmation');
  $receipts=get_option('uplink_mbb_import_receipts');$receipt=$receipts['wicked-folders'];$dest=$receipt['map'][$terms[0]];
  $changed=$receipts;$changed['wicked-folders']['blog_id']+=100;update_option('uplink_mbb_import_receipts',$changed,false);fc_check(!Cleanup::preview('wicked-folders')['available'],'Receipt from another site rejected');update_option('uplink_mbb_import_receipts',$receipts,false);
  wp_remove_object_terms($images[0],$dest,'mbb_folder');fc_check(!Cleanup::preview('wicked-folders')['available'],'Cleanup refuses an assignment missing from Media Bridge');wp_set_object_terms($images[0],[(int)$dest],'mbb_folder',true);
  register_taxonomy($tax,'attachment',['hierarchical'=>true]);$post=wp_insert_post(['post_title'=>$tag.' unrelated post','post_status'=>'publish']);$posts[]=$post;wp_set_object_terms($post,[$terms[0]],$tax);unregister_taxonomy($tax);fc_check(!Cleanup::preview('wicked-folders')['available'],'Non-media source relationships prevent cleanup');register_taxonomy($tax,'attachment',['hierarchical'=>true]);wp_remove_object_terms($post,$terms[0],$tax);unregister_taxonomy($tax);
  $users[]=wp_insert_user(['user_login'=>'mbb_cleanup_'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(32),'role'=>'author']);wp_set_current_user(end($users));fc_check(is_wp_error(Cleanup::start('wicked-folders',true)),'Authors cannot clear legacy data');wp_set_current_user(1);
  update_option('active_plugins',array_merge($saved['active_plugins'],[$fakefile]));fc_check(!Cleanup::preview('wicked-folders')['available'],'Manual cleanup blocks an active source plugin');
  $denier=static fn($caps,$cap)=>$cap==='activate_plugins'?['do_not_allow']:$caps;add_filter('map_meta_cap',$denier,10,2);fc_check(is_wp_error(Cleanup::retire('wicked-folders',$j['id'])),'Deactivation requires plugin activation permission');remove_filter('map_meta_cap',$denier);$denier=null;
  $r=Cleanup::retire('wicked-folders',$j['id']);fc_check(!is_wp_error($r)&&!is_plugin_active($fakefile),'Optional single-site retirement deactivates the source');
  fc_check(Cleanup::preview('wicked-folders')['available'],'Verified inactive source becomes available for cleanup');$clean=Cleanup::start('wicked-folders',true);fc_check(!is_wp_error($clean),'Confirmed cleanup starts');fc_check(is_wp_error(Import::start(true,false,'happyfiles')),'Import cannot run during cleanup');fc_check(is_wp_error(Cleanup::step('wrong-id')),'Wrong cleanup identifier rejected');$clean=fc_finish($clean);
  fc_check(!Sources::terms('wicked-folders'),'Legacy folders removed');fc_check(!get_term_meta($terms[0]),'Source folder metadata removed');
  foreach($images as$id)fc_check(get_post($id)&&has_term((int)$dest,'mbb_folder',$id)&&get_post_meta($id,'mbb_unrelated',true)==='preserve','Attachment, destination assignments and unrelated metadata preserved');
  fc_check(!get_post_meta($images[0],'_wicked_folder_order__wf_attachment_folders__'.$terms[0],true),'Known Wicked source ordering metadata cleared');fc_check(get_post($post)!==null,'Unrelated post preserved');fc_check(!Cleanup::preview('wicked-folders')['available'],'Cleared source is no longer offered for cleanup');
  // Network behavior is simulated; data operations still run through real WordPress APIs.
  register_taxonomy($tax,'attachment',['hierarchical'=>true]);$net=wp_insert_term($tag.' network',$tax)['term_id'];$terms[]=$net;wp_set_object_terms($images[0],[$net],$tax);$GLOBALS['mbb_mock_network']=[$fakefile];$denier=static fn($caps,$cap)=>$cap==='activate_plugins'?['do_not_allow']:$caps;add_filter('map_meta_cap',$denier,10,2);$j=fc_import();$r=Cleanup::retire('wicked-folders',$j['id']);fc_check(!is_wp_error($r)&&$r['network_preserved']&&Sources::network_active('wicked-folders'),'Network activation preserved during optional retirement without network activation permission');remove_filter('map_meta_cap',$denier);$denier=null;
  $clean=Cleanup::start('wicked-folders',true);fc_check(!is_wp_error($clean),'Site-local cleanup can proceed with preserved network activation');fc_finish($clean);
  fc_check(taxonomy_exists($tax)&&Sources::plugin_status('wicked-folders')==='active','Network-owned taxonomy registration and active status preserved');fc_check(get_site_option('active_sitewide_plugins')===$network,'Network activation option unchanged');fc_check(get_option('active_plugins')===$saved['active_plugins'],'Unrelated site plugin activations unchanged');
  echo "Folder cleanup and plugin-status tests passed.\n";
 }finally{
  wp_set_current_user(1);if($denier)remove_filter('map_meta_cap',$denier);$GLOBALS['mbb_mock_network']=[];
  if(!taxonomy_exists($tax))register_taxonomy($tax,'attachment',['hierarchical'=>true]);foreach($images as$id)wp_delete_attachment($id,true);foreach($posts as$id)wp_delete_post($id,true);foreach(array_reverse($terms)as$id)wp_delete_term($id,$tax);if(!$registered)unregister_taxonomy($tax);
  $now=get_terms(['taxonomy'=>'mbb_folder','hide_empty'=>false,'fields'=>'ids']);foreach(array_reverse(array_diff($now,$targets))as$id)wp_delete_term($id,'mbb_folder');require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as$id)wp_delete_user($id);
  foreach($saved as$n=>$v){if($v===false)delete_option($n);else update_option($n,$v,false);}if($oldcache===false)wp_cache_delete('plugins','plugins');else wp_cache_set('plugins',$oldcache,'plugins');delete_transient('uplink_mbb_media_health_v3');
 }
}
