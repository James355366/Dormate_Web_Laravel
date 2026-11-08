@php dm_layout_start($u, ucwords(str_replace('_', ' ', $page)), $page); @endphp
@yield('content')
@php dm_layout_end(); @endphp
