<?php
use UplinkPress\MediaBridgeForBricks\Folder_Import;
use UplinkPress\MediaBridgeForBricks\Folder_Provider;
use UplinkPress\MediaBridgeForBricks\Native_Media_Library;
use UplinkPress\MediaBridgeForBricks\Bricks_Folder_Provider;
wp_set_current_user(1);
$tax='happyfiles_category';
if(!taxonomy_exists($tax)) register_taxonomy($tax,'attachment',['hierarchical'=>true,'update_count_callback'=>'_update_generic_term_count']);
$path=sys_get_temp_dir().'/mbb-nested-import-fixture.json';
$mode=$args[0]??'audit';
function mbb_nested_checked($value){if(is_wp_error($value))throw new RuntimeException($value->get_error_message());return $value;}
if($mode==='setup'){
$f=['source_terms'=>[],'images'=>[],'old_targets'=>get_terms(['taxonomy'=>'mbb_folder','hide_empty'=>false,'fields'=>'ids']),'options'=>[],'levels'=>[]];
foreach(['uplink_mbb_happyfiles_import','uplink_mbb_import_receipts']as$n)$f['options'][$n]=get_option($n);
$tag='Deep import verification '.wp_generate_password(8,false);
$root=mbb_nested_checked(wp_insert_term($tag,$tax))['term_id'];$f['source_terms'][]=$root;$f['levels'][]=$root;
$first=mbb_nested_checked(wp_insert_term('First child',$tax,['parent'=>$root]))['term_id'];$f['source_terms'][]=$first;$f['levels'][]=$first;
for($i=0;$i<30;$i++)$f['source_terms'][]=mbb_nested_checked(wp_insert_term('Padding '.$i,$tax,['parent'=>$root]))['term_id'];
$second=mbb_nested_checked(wp_insert_term('Second child',$tax,['parent'=>$first]))['term_id'];$f['source_terms'][]=$second;$f['levels'][]=$second;
$third=mbb_nested_checked(wp_insert_term('Third child',$tax,['parent'=>$second]))['term_id'];$f['source_terms'][]=$third;$f['levels'][]=$third;
foreach([1,7,113,211]as$level=>$count)for($i=0;$i<$count;$i++){
$id=mbb_nested_checked(wp_insert_attachment(['post_title'=>$tag.' depth '.$level.' image '.$i,'post_mime_type'=>'image/jpeg','post_status'=>'inherit','post_author'=>1],false,0));$f['images'][]=$id;mbb_nested_checked(wp_set_object_terms($id,[$f['levels'][$level]],$tax));
}
file_put_contents($path,wp_json_encode($f));
$j=mbb_nested_checked(Folder_Import::start(true,false,'happyfiles',false));$j=mbb_nested_checked(Folder_Import::step($j['id']));
if($j['cursor']!==30||$j['phase']!=='folders')throw new RuntimeException('Expected folder batch checkpoint');
echo "PASS: Saved first folder batch at 30; secondary and tertiary descendants remain for next request.\n";
}elseif($mode==='step'){
$j=mbb_nested_checked(Folder_Import::status());$j=mbb_nested_checked(Folder_Import::step($j['id']));echo wp_json_encode($j)."\n";
}elseif($mode==='finish'){
$j=mbb_nested_checked(Folder_Import::status());while($j['status']==='running')$j=mbb_nested_checked(Folder_Import::step($j['id']));echo wp_json_encode($j)."\n";
}elseif($mode==='audit'){
$f=json_decode(file_get_contents($path),true);$j=get_option('uplink_mbb_happyfiles_import');$f['map']=$j['map'];file_put_contents($path,wp_json_encode($f));
$native=new Native_Media_Library(new Folder_Provider(),false);$method=new ReflectionMethod($native,'native_collection_data');$data=array_column($method->invoke($native),null,'id');$bricks=array_column((new Bricks_Folder_Provider())->get_folders(),null,'id');
foreach($f['source_terms']as$s){$t=(int)$j['map'][$s];$src=array_map('intval',get_objects_in_term($s,$tax));$dst=array_map('intval',get_objects_in_term($t,'mbb_folder'));sort($src);sort($dst);$sp=(int)get_term($s,$tax)->parent;$expected=$sp?(int)$j['map'][$sp]:0;
if($src!==$dst||(int)get_term($t,'mbb_folder')->parent!==$expected||(int)get_term($t,'mbb_folder')->count!==count($src)||(int)$data[$t]['count']!==count($src)||(int)$bricks[$t]['count']!==count($src))throw new RuntimeException('Mismatch at '.$s);
if(in_array($s,$f['levels'],true))echo 'PASS: depth '.array_search($s,$f['levels'],true).' '.get_term($s,$tax)->name.' — '.count($src)." exact assignments; parent mapping, stored, WP and Bricks counts correct.\n";
}
if($j['status']!=='complete'||$j['assigned']+$j['existing']!==332||$j['denied'])throw new RuntimeException('Expected complete 332 relationships');echo "PASS: 34 folders, 332 relationships; no missing IDs or permission skips.\n";
}elseif($mode==='cleanup'){
$f=json_decode(file_get_contents($path),true);$j=get_option('uplink_mbb_happyfiles_import');$map=$f['map']??$j['map'];
foreach($f['images']as$id)wp_delete_attachment($id,true);
foreach(array_reverse($f['source_terms'])as$id)wp_delete_term($id,$tax);
foreach(array_reverse(array_unique(array_values($map)))as$id)if(!in_array((int)$id,array_map('intval',$f['old_targets']),true))wp_delete_term((int)$id,'mbb_folder');
if(isset($j['map'][$f['levels'][0]]))foreach($f['options']as$n=>$v){if($v===false)delete_option($n);else update_option($n,$v,false);}
delete_transient('uplink_mbb_media_health_v3');echo "PASS: Removed only disposable nested test data; prior import state restored.\n";
}
