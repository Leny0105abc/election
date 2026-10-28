@php($school = school())
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="{{ $school->primary_color }}">
<title>{{ isset($title) && $title ? $title.' · ' : '' }}SchoolVote · {{ $school->name }}</title>
@if ($school->logoUrl())
    <link rel="icon" href="{{ $school->logoUrl() }}">
@endif
<style>
    :root { --brand: {{ $school->primary_color }}; --brand-accent: {{ $school->secondary_color }}; }
</style>
@vite(['resources/css/app.css', 'resources/js/app.js'])
