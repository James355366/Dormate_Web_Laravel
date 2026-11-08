@extends('layouts.app')
@section('content')
<?php
dm_allow_roles($u,['RESIDENT']);dm_heading('Report a repair','Tell us what needs attention in your room.');echo '<section class="panel form-panel">';dm_begin_form('submit_report','report_form',[],true);dm_field('title','Issue title','','text','required maxlength="120"');dm_textarea_field('description','Describe the issue','',4000);dm_field('photo','Photo (optional; 2 MB max)','','file','accept="image/jpeg,image/png,image/webp"');dm_submit_button('Submit report');echo '</form></section>';
?>
@endsection
