@extends('layouts.app')
@section('content')
<?php
dm_heading('Change password','Choose a password with at least 8 characters.');echo '<section class="panel form-panel">';dm_begin_form('password','password');dm_field('current_password','Current password','','password','required autocomplete="current-password"');dm_field('password','New password','','password','required minlength="8" maxlength="72" autocomplete="new-password"');dm_field('password_confirm','Confirm password','','password','required autocomplete="new-password"');dm_submit_button('Update password');echo '</form></section>';
?>
@endsection
