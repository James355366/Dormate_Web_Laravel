@extends('layouts.app')
@section('content')
<?php
dm_allow_roles($u,['DORM_ADMIN','SUPER_ADMIN']);$id=(int)(request()->query('id')??0);$p=$id?\App\Models\Announcement::visibleTo($u)->whereKey($id)->where('posted_by',$u['id'])->first()?->getAttributes():null;if($id&&!$p)throw new RuntimeException('Announcement not found.');dm_heading($id?'Edit announcement':'Post an announcement',$u['role']==='SUPER_ADMIN'?'These announcements are visible to Super Admin accounts only.':'Only your dorm community can see this announcement.');echo '<section class="panel form-panel">';dm_begin_form('save_post','post_form',['id'=>$id]);dm_hidden('id',$id);dm_field('title','Title',$p['title']??'','text','required maxlength="140"');dm_textarea_field('body','Message',$p['body']??'',10000);dm_submit_button('Publish announcement');echo '</form></section>';
?>
@endsection
