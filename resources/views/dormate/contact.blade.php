@extends('layouts.app')
@section('content')
<?php
$d=dm_one('SELECT u.name,u.email,u.phone FROM dorms d JOIN users u ON u.id=d.owner_id WHERE d.id=?',[$u['dorm_id']]);dm_heading('Contact your administrator','We are here to help.');echo '<section class="panel form-panel">';if($d){dm_detail('Name',$d['name']);dm_detail('Email',$d['email']);dm_detail('Phone',$d['phone']?:'Not provided');}else echo '<p>No administrator details available.</p>';echo '</section>';
?>
@endsection
