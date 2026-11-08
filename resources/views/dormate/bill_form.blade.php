@extends('layouts.app')
@section('content')
<?php
dm_allow_roles($u,['DORM_ADMIN']);dm_heading('Generate a bill','Create a monthly statement for a resident.');$opts=[];foreach(dm_all("SELECT id,name FROM users WHERE dorm_id=? AND role='RESIDENT' AND status='active' AND room_id IS NOT NULL",[$u['dorm_id']]) as $r)$opts[$r['id']]=$r['name'];echo '<section class="panel form-panel">';dm_begin_form('generate_bill','bill_form');dm_select_field('resident_id','Resident',$opts,'','required');dm_field('billing_month','Billing month',date('Y-m'),'month','required');dm_field('due_date','Due date',date('Y-m-t'),'date','required');foreach(['room_rent'=>'Room rent','electricity'=>'Electricity','water'=>'Water','wifi'=>'Wi-Fi'] as $k=>$v)dm_field($k,$v,'0.00','number','min="0" step="0.01" required');dm_submit_button('Generate bill');echo '</form></section>';
?>
@endsection
