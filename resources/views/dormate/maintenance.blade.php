@extends('layouts.app')
@section('content')
<?php
dm_allow_roles($u,['RESIDENT','DORM_ADMIN','MAINTENANCE_STAFF']);[$w,$a]=dm_report_scope($u,'m');$f=(string)(request()->query('filter')??'all');if(in_array($f,['PENDING','IN_PROGRESS','RESOLVED'],true)){$w.=' AND m.status=?';$a[]=$f;}$rows=dm_all("SELECT m.*,r.room_number,u.name AS resident_name,s.name AS staff_name FROM maintenance_reports m JOIN rooms r ON r.id=m.room_id JOIN users u ON u.id=m.resident_id LEFT JOIN users s ON s.id=m.assigned_staff_id WHERE $w ORDER BY m.created_at DESC",$a);dm_heading($u['role']==='MAINTENANCE_STAFF'?'My work queue':'Maintenance','A better place to live, one repair at a time.',$u['role']==='RESIDENT'?dm_link_button('Submit report','report_form',[],'primary','plus'):'');dm_search_filters('maintenance',['all'=>'All requests','PENDING'=>'Pending','IN_PROGRESS'=>'In progress','RESOLVED'=>'Resolved'],$f,'','');echo '<section class="panel">';dm_reports_table($rows);echo '</section>';
?>
@endsection
