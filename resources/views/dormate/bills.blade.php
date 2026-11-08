@extends('layouts.app')
@section('content')
<?php
dm_allow_roles($u,['RESIDENT','DORM_ADMIN']);[$where,$args]=dm_bill_scope($u,'b');$rows=dm_all("SELECT b.*,u.name,r.room_number FROM bills b JOIN users u ON u.id=b.resident_id JOIN rooms r ON r.id=b.room_id WHERE $where ORDER BY b.billing_month DESC,b.id DESC",$args);dm_heading('Bills & payments','Monthly statements, all in one place.',dm_is_admin($u)?dm_link_button('Generate bill','bill_form',[],'primary','plus'):'');echo '<div class="row g-4">';foreach($rows as $b){echo '<div class="col-md-6 col-xl-4"><section class="panel p-4"><div class="d-flex justify-content-between"><h2>'.dm_esc(date('M Y',strtotime($b['billing_month']))).'</h2>'.dm_bill_badge($b).'</div><p>'.dm_esc($b['name']).' · Room '.dm_esc($b['room_number']).'</p><div class="bill-total">'.dm_money(dm_effective_cents($b)/100).'</div><p class="text-muted">Due '.dm_pretty_date($b['due_date']).'</p>'.dm_link_button('View statement','bill',['id'=>$b['id']],'primary').'</section></div>';}if(!$rows)dm_empty_state('No bills yet','Your monthly statements will appear here.');echo '</div>';
?>
@endsection
