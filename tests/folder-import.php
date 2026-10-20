<?php
/** Media-only taxonomy import contracts; creates and removes its own local-site fixtures. */
use UplinkPress\MediaBridgeForBricks\Folder_Import as Import;
use UplinkPress\MediaBridgeForBricks\Folder_Import_Sources as Sources;
wp_set_current_user(1);
function fi_check($ok,string $label):void { if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n"; }
function fi_complete(array $job):array { for($i=0;$i<100 && $job['status']==='running';++$i){$job=Import::step($job['id']);if(is_wp_error($job))throw new RuntimeException($job->get_error_message());}fi_check($job['status']==='complete','Import completes in bounded batches');return $job; }
function fi_target(string $source,int $id):int { $meta=$source==='happyfiles'?'_uplink_mbb_happyfiles_source':'_uplink_mbb_import_source';$value=$source==='happyfiles'?(string)$id:$source.':'.$id;$ids=get_terms(array('taxonomy'=>'mbb_folder','hide_empty'=>false,'meta_key'=>$meta,'meta_value'=>$value,'fields'=>'ids'));return (int)($ids[0]??0); }
$savedjob=get_option('uplink_mbb_happyfiles_import');$savedsettings=get_option('uplink_mbb_settings');$oldtargets=get_terms(array('taxonomy'=>'mbb_folder','hide_empty'=>false,'fields'=>'ids'));$createdterms=[];$createdtax=[];$images=[];$posts=[];$tag='MBB sources '.wp_generate_uuid4();$deny=null;
try {
 fi_check(is_wp_error(Import::start(true,false,'../users'))&&is_wp_error(Import::preview('not-a-provider')),'Unsupported sources cannot select arbitrary taxonomies');
 foreach(Sources::definitions() as $key=>$definition){
  $tax=$definition['taxonomy'];if(!taxonomy_exists($tax)){register_taxonomy($tax,'attachment',array('hierarchical'=>true));$createdtax[]=$tax;}
  $parent=wp_insert_term($tag.' '.$key,'mbb_folder')['term_id']; // A preexisting destination must be reused.
  $root=0;
  if($key==='wp-media-folder'){$existing=get_term_by('slug','wp-media-folder-root',$tax);if($existing)$root=$existing->term_id;else{$root=wp_insert_term('Media root',$tax,array('slug'=>'wp-media-folder-root'))['term_id'];$createdterms[]=[$root,$tax];}}
  $a=wp_insert_term($tag.' '.$key,$tax,array('parent'=>$root))['term_id'];$createdterms[]=[$a,$tax];
  $b=wp_insert_term($tag.' nested '.$key,$tax,array('parent'=>$a))['term_id'];$createdterms[]=[$b,$tax];
  $empty=wp_insert_term($tag.' empty '.$key,$tax,array('parent'=>$a))['term_id'];$createdterms[]=[$empty,$tax];
  $id=wp_insert_attachment(array('post_title'=>$tag.' '.$key,'post_status'=>'inherit','post_mime_type'=>'image/jpeg','post_author'=>1));$images[]=$id;wp_set_object_terms($id,[$a,$b],$tax);
  $post=wp_insert_post(array('post_title'=>$tag.' excluded post','post_status'=>'publish'));$posts[]=$post;wp_set_object_terms($post,[$a],$tax);
  if(in_array($tax,$createdtax,true))unregister_taxonomy($tax);
  $preview=Import::preview($key);fi_check(!is_wp_error($preview)&&$preview['folders']>=3&&$preview['assignments']>=2,$definition['label'].' records detected with source plugin inactive');
  $before=Sources::terms($key);$job=Import::start(true,false,$key);fi_check(!is_wp_error($job)&&$job['source']===$key,'Selected provider is stored in the job');$job=fi_complete($job);
  fi_check(fi_target($key,$a)===(int)$parent,'Matching destination reused');$child=fi_target($key,$b);
  fi_check($child && (int)get_term($child,'mbb_folder')->parent===(int)$parent && fi_target($key,$empty)>0,'Nested and empty folders copied');
  fi_check(has_term((int)$parent,'mbb_folder',$id)&&has_term($child,'mbb_folder',$id),'Multiple attachment assignments copied');
  fi_check(!has_term((int)$parent,'mbb_folder',$post),'Post and page relationships excluded');
  fi_check(wp_json_encode($before)===wp_json_encode(Sources::terms($key)),'Source folder records remain unchanged');
  $repeat=fi_complete(Import::start(true,false,$key));fi_check($repeat['created']===0&&$repeat['assigned']===0,'Repeat run creates no duplicate folders or assignments');
  if($key==='wp-media-folder')fi_check(fi_target($key,$root)===0&&(int)get_term($parent,'mbb_folder')->parent===0,'Synthetic WP Media Folder root excluded and children promoted');
  if(in_array($tax,$createdtax,true))register_taxonomy($tax,'attachment',array('hierarchical'=>true));
 }
 $tax='wf_attachment_folders';
 for($i=0;$i<34;++$i){$t=wp_insert_term($tag.' batch '.$i,$tax)['term_id'];$createdterms[]=[$t,$tax];$lastbatch=$t;update_term_meta($t,'wf_order',34-$i);if($i===0)$large=$t;}
 for($i=0;$i<105;++$i){$id=wp_insert_attachment(array('post_title'=>$tag.' batch '.$i,'post_status'=>'inherit','post_mime_type'=>'image/jpeg','post_author'=>1));$images[]=$id;wp_set_object_terms($id,[$large],$tax);}
 $job=Import::start(true,false,'wicked-folders');fi_check(is_wp_error(Import::start(true,false,'happyfiles')),'Cannot switch source during an active import');
 $first=Import::step($job['id']);fi_check($first['cursor']===30&&$first['status']==='running','Folder phase stops at 30');
 $resumed=Import::start(false,true,'wicked-folders');fi_check($resumed['id']===$job['id']&&$resumed['media']===true&&$resumed['select_provider']===false,'Resume keeps original options and source');
 $denied=end($images);$deny=static function($caps,$cap,$user,$args)use($denied){return $cap==='edit_post'&&(int)($args[0]??0)===$denied?['do_not_allow']:$caps;};add_filter('map_meta_cap',$deny,10,4);
 $before=0;$current=$first;
 while($current['status']==='running'){$next=Import::step($current['id']);fi_check($next['assigned']-$current['assigned']<=100,'Assignment phase respects 100-item request limit');$current=$next;}
 fi_check($current['denied']>=1&&!has_term(fi_target('wicked-folders',$large),'mbb_folder',$denied),'Each assignment checks edit permission');remove_filter('map_meta_cap',$deny);$deny=null;
 $batch=fi_target('wicked-folders',$large);fi_check(has_term($batch,'mbb_folder',$images[count($images)-2]),'Final assignment batch copied');
 $ordered=get_terms(array('taxonomy'=>'mbb_folder','hide_empty'=>false,'meta_key'=>'_uplink_mbb_import_source','meta_value'=>'wicked-folders:'.$large));fi_check((int)get_term_meta($batch,'_uplink_mbb_position',true)>(int)get_term_meta(fi_target('wicked-folders',$lastbatch),'_uplink_mbb_position',true),'New Wicked Folders siblings preserve wf_order');
 $j=Import::start(false,false,'wicked-folders');$stored=get_option('uplink_mbb_happyfiles_import');$sourceid=$stored['plan'][0]['id'];$originalparent=get_term($sourceid,$tax)->parent;wp_update_term($sourceid,$tax,array('parent'=>$large===$sourceid?$a:$large));$j=Import::step($j['id']);fi_check($j['status']==='error','Hierarchy changes during import stop safely');wp_update_term($sourceid,$tax,array('parent'=>$originalparent));
 echo "Folder import adapter tests passed.\n";
} finally {
 wp_set_current_user(1);if($deny)remove_filter('map_meta_cap',$deny);foreach($images as $id)wp_delete_attachment($id,true);foreach($posts as $id)wp_delete_post($id,true);
 foreach($createdtax as $tax)if(!taxonomy_exists($tax))register_taxonomy($tax,'attachment',array('hierarchical'=>true));foreach(array_reverse($createdterms)as [$id,$tax])wp_delete_term($id,$tax);
 $targets=get_terms(array('taxonomy'=>'mbb_folder','hide_empty'=>false,'fields'=>'ids'));foreach(array_reverse(array_diff($targets,$oldtargets))as$id)wp_delete_term($id,'mbb_folder');foreach($createdtax as$tax)unregister_taxonomy($tax);
 update_option('uplink_mbb_settings',$savedsettings);if($savedjob===false)delete_option('uplink_mbb_happyfiles_import');else update_option('uplink_mbb_happyfiles_import',$savedjob,false);delete_transient('uplink_mbb_media_health_v3');
}
