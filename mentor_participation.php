<?php
require('./common.php');

$opts = getOptions($QUERY);
if(!$opts['city_id']) $opts['city_id'] = 1;
extract($opts);

if(!isset($QUERY['type'])) $type = 'mentor';
else $type = $QUERY['type'];

$page_title = ucfirst(format($type)) . ' Participation';

$year = findYear($to);
$center_check = '';
if($center_id) $center_check = " AND C.id=$center_id";

// Cache a few things in the beginning itself.
$all_batches = $sql->getById("SELECT B.id, B.batch_head_id, B.center_id FROM Batch B INNER JOIN Center C ON C.id=B.center_id WHERE C.status='1' AND C.city_id=$city_id $center_check");
$first_classes = $sql->getById("SELECT B.center_id,MIN(C.class_on) FROM Class C 
									INNER JOIN Batch B ON C.batch_id=B.id
									INNER JOIN Data D ON D.item_id=C.id
									WHERE D.item='Class' AND D.name='mentor_attendance' AND C.batch_id IN (" . implode(",", array_keys($all_batches)) . ") 
									GROUP BY B.center_id");

$first_class = min(array_values($first_classes));

// Main data pull.
$class_data = $sql->getAll("SELECT C.id,C.class_on,C.batch_id,C.level_id,D.data 
									FROM Class C 
									JOIN Data D ON D.item_id=C.id
									WHERE D.item='Class' AND D.name='mentor_attendance' AND C.batch_id IN (" . implode(",", array_keys($all_batches)) . ") AND C.class_on >  '$first_class'
									ORDER BY C.class_on");

$mentor_group_id = 8;
if($project_id == 2) $mentor_group_id = 286;
if($type == 'mentor') {
	$monitors = $sql->getById("SELECT U.id,U.name,U.phone,'0' AS attended, '0' AS attended_own_batch FROM User U
									INNER JOIN UserGroup UG ON UG.user_id=U.id
									WHERE UG.group_id = $mentor_group_id AND UG.year=2018 AND U.status='1' AND U.user_type='volunteer' AND U.city_id=$city_id
									ORDER BY U.name");
} else {
	if($type == 'es_fellows') 
		$fellow_groups = implode(",", $sql->getCol("SELECT id FROM `Group` WHERE status='1' AND group_type='normal' AND type='fellow' AND vertical_id=3"));
	else 
		$fellow_groups = implode(",", $sql->getCol("SELECT id FROM `Group` WHERE status='1' AND group_type='normal' AND type='fellow'"));
	$monitors = $sql->getById("SELECT U.id,U.name,U.phone,'0' AS attended FROM User U
									INNER JOIN UserGroup UG ON UG.user_id=U.id
									WHERE UG.group_id IN ( $fellow_groups ) AND UG.year=2018 AND U.status='1' AND U.user_type='volunteer' AND U.city_id=$city_id
									ORDER BY U.name");
}

foreach($class_data as $cls) {
	$attend = json_decode($cls['data']);

	foreach ($attend as $user_id => $attended) {
		if($attended and isset($monitors[$user_id])) {
			$monitors[$user_id]['attended']++;

			if($type == 'mentor')
				if($all_batches[$cls['batch_id']]['batch_head_id'] == $user_id)	$monitors[$user_id]['attended_own_batch']++;
		} 
	}
}

// Sort by Shelter name.
usort($monitors, function($a, $b) {
	global $all_batches, $all_centers; 

	$batch_a = $batch_b = [];
	foreach ($all_batches as $batch_id => $batch_info) {
		if($batch_info['batch_head_id'] == $a['id']) $batch_a = $batch_info;
		if($batch_info['batch_head_id'] == $b['id']) $batch_b = $batch_info;
	}

	if($batch_a and $batch_b)
		return strcmp($all_centers[$batch_a['center_id']]['name'], $all_centers[$batch_b['center_id']]['name']);
	
	return 0;
});

render();
