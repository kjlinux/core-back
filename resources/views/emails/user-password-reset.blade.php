@extends('emails.layout')

@section('content')
<p class="greeting">Bonjour, {{ $user->first_name }} {{ $user->last_name }},</p>

<p class="text">
  Votre mot de passe sur la plateforme <strong>TangaFlow</strong> a été réinitialisé
  par un administrateur. Voici votre nouveau mot de passe temporaire :
</p>

<div class="password-box">
  <span>{{ $password }}</span>
</div>

<p class="text">
  Utilisez ce mot de passe pour vous connecter, puis changez-le immédiatement depuis votre
  profil pour sécuriser votre compte.
</p>

<div class="info-box">
  <table>
    <tr>
      <td>Email de connexion</td>
      <td>{{ $user->email }}</td>
    </tr>
  </table>
</div>

<p class="text" style="color:#dc2626; font-size:13px;">
  Si vous n'avez pas demandé cette réinitialisation, contactez votre administrateur sans délai.
</p>
@endsection
