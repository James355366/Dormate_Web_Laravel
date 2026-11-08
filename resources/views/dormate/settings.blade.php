@extends('layouts.app')
@section('content')
<?php
dm_allow_roles($u,['DORM_ADMIN']);$d=dm_one('SELECT * FROM dorms WHERE id=?',[$u['dorm_id']]);dm_heading('Dorm settings','Your community details and payment instructions.');echo '<section class="panel form-panel">';dm_begin_form('dorm_settings','settings',[],true);dm_field('dorm_name','Dorm name',$d['name'],'text','required');dm_field('address','Address',$d['address']);dm_field('gcash_name','GCash account name',$d['gcash_name']);dm_field('gcash_number','GCash mobile number',$d['gcash_number'],'tel');dm_field('qr','GCash QR image (optional; 2 MB max)','','file','accept="image/jpeg,image/png,image/webp"');dm_submit_button('Save settings');echo '</form></section>';
?>
@endsection
