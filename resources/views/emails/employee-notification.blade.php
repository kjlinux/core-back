@extends('emails.layout')

@section('content')
<p class="greeting">Bonjour {{ $employee->first_name }} {{ $employee->last_name }},</p>

<p class="text" style="font-size:16px; font-weight:600; color:#1e293b;">{{ $title }}</p>

<p class="text">{!! nl2br(e($body)) !!}</p>

<hr class="divider">

<p class="text" style="font-size:13px; color:#64748b;">
  Retrouvez le détail dans votre <strong>espace personnel</strong> sur la plateforme TangaFlow,
  rubrique <strong>Mon espace</strong>.
</p>
@endsection
