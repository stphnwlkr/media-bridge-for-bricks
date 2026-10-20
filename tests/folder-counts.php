<?php
/** Folder counters must describe media relationships independently of post publication. */
use UplinkPress\MediaBridgeForBricks\Folder_Provider;
use UplinkPress\MediaBridgeForBricks\Native_Media_Library;
use UplinkPress\MediaBridgeForBricks\Bricks_Folder_Provider;
use UplinkPress\MediaBridgeForBricks\Plugin;
wp_set_current_user(1);$posts=[];$images=[];$terms=[];$option=get_option('uplink_mbb_folder_count_version');$settings=get_option('uplink_mbb_settings');$tag='Folder count regression '.wp_generate_uuid4();
function count_check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
try{
 count_check(get_taxonomy('mbb_folder')->update_count_callback==='_update_generic_term_count','Folder taxonomy uses relationship-based counters');
 $root=wp_insert_term($tag,'mbb_folder')['term_id'];$terms[]=$root;
 foreach(['unattached','draft','private','publish']as$status){
  $parent=0;if($status!=='unattached'){$parent=wp_insert_post(['post_title'=>$tag.' '.$status,'post_status'=>$status,'post_author'=>1]);$posts[]=$parent;}
  $child=wp_insert_term($tag.' '.$status,'mbb_folder',['parent'=>$root])['term_id'];$terms[]=$child;
  $id=wp_insert_attachment(['post_title'=>$tag.' '.$status,'post_mime_type'=>'image/jpeg','post_status'=>'inherit','post_author'=>1],false,$parent);$images[]=$id;
  wp_set_object_terms($id,[$child],'mbb_folder');count_check(get_term($child,'mbb_folder')->count===1,$status.' image is counted');
 }
 wp_set_object_terms($images[0],[(int)$root],'mbb_folder',true);
 Plugin::update_settings(array_merge(Plugin::settings(),['parent_count_display'=>'direct_total']));
 $library=new Native_Media_Library(new Folder_Provider(),false);$read=new ReflectionMethod($library,'native_collection_data');$data=array_column($read->invoke($library),null,'id');
 count_check($data[$root]['count']===1&&$data[$root]['totalCount']===4,'Cumulative parent count deduplicates shared media');
 $bricks=array_column((new Bricks_Folder_Provider())->get_folders(),null,'id');foreach(array_slice($terms,1)as$id)count_check($data[$id]['count']===1&&$bricks[$id]['count']===1,'WordPress and Bricks expose the same child count');
 $legacy=get_term($terms[1],'mbb_folder');_update_post_term_count([$legacy->term_taxonomy_id],get_taxonomy('mbb_folder'));clean_term_cache([$terms[1]],'mbb_folder');count_check(get_term($terms[1],'mbb_folder')->count===0,'Old callback reproduces zero for an assigned unattached image');
 delete_option('uplink_mbb_folder_count_version');wp_set_current_user(0);(new Folder_Provider())->maybe_recount();count_check(get_option('uplink_mbb_folder_count_version')===false,'Anonymous requests cannot trigger recount migration');
 wp_set_current_user(1);$relationships=get_objects_in_term($terms,'mbb_folder');(new Folder_Provider())->maybe_recount();count_check(get_term($terms[1],'mbb_folder')->count===1&&get_option('uplink_mbb_folder_count_version')==='1','Administrator migration repairs existing counts');count_check($relationships===get_objects_in_term($terms,'mbb_folder'),'Recount preserves all assignments');
 wp_delete_attachment(array_pop($images),true);count_check(get_term(end($terms),'mbb_folder')->count===0,'Deleting media updates folder counts');
 echo "Folder count regression tests passed.\n";
}finally{wp_set_current_user(1);foreach($images as$id)wp_delete_attachment($id,true);foreach($posts as$id)wp_delete_post($id,true);foreach(array_reverse($terms)as$id)wp_delete_term($id,'mbb_folder');update_option('uplink_mbb_settings',$settings,false);if($option===false)delete_option('uplink_mbb_folder_count_version');else update_option('uplink_mbb_folder_count_version',$option,false);}
