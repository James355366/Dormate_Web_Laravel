<!doctype html>
<html lang="en" data-bs-theme="{{ dm_current_theme() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ dm_current_theme() === 'dark' ? '#15212c' : '#edf1f3' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · DorMate</title>
    <link rel="icon" href="{{ asset('assets/dormate-logo.webp') }}" type="image/webp">
    <link rel="stylesheet" href="{{ asset('assets/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    @if($stylesheet)<link rel="stylesheet" href="{{ asset('assets/'.$stylesheet) }}">@endif
    <link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
</head>
<body><a class="skip-link" href="#main">Skip to content</a>
