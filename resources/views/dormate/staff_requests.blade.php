@extends('layouts.app')
@section('content')
<?php
dm_allow_roles($u,['DORM_ADMIN']);$rows=dm_all("SELECT id,name,email,mobile_number,phone,created_at FROM users WHERE dorm_id=? AND role='MAINTENANCE_STAFF' AND status='pending' ORDER BY created_at",[$u['dorm_id']]);
 dm_heading('Staff registrations','Review the maintenance staff who selected you as their managing dorm administrator.');
 echo '<div class="row g-4">';if(!$rows)dm_empty_state('No pending staff registrations','New maintenance staff requests for your dorm will appear here.','users');
 foreach($rows as $r){echo '<div class="col-lg-6"><section class="panel p-4"><h2>'.dm_esc($r['name']).'</h2>'.dm_badge('pending');dm_detail('Email',$r['email']?:'Not provided');if(!empty($r['mobile_number']))dm_detail('Mobile sign-in',$r['mobile_number']);dm_detail('Contact',$r['phone']?:'Not provided');dm_detail('Registered',dm_pretty_date($r['created_at']));dm_staff_review_forms((int)$r['id'],'staff_requests');echo '</section></div>';}echo '</div>';
?>
@endsection
