@extends('emails.layout')

@section('content')
<p class="greeting">Bienvenue, {{ $employee->first_name }} {{ $employee->last_name }} !</p>

<p class="text">
  Votre compte employe a ete cree sur la plateforme <strong>Core Tanga Group</strong>
  par votre entreprise. Vous pouvez desormais consulter vos fiches de paie, vos presences
  et soumettre vos justificatifs d'absence en ligne.
</p>

<div class="info-box">
  <table>
    <tr>
      <td>Matricule</td>
      <td>{{ $employee->employee_number }}</td>
    </tr>
    <tr>
      <td>Poste</td>
      <td>{{ $employee->position }}</td>
    </tr>
    @if($employee->site)
    <tr>
      <td>Site</td>
      <td>{{ $employee->site->name }}</td>
    </tr>
    @endif
    @if($employee->department)
    <tr>
      <td>Departement</td>
      <td>{{ $employee->department->name }}</td>
    </tr>
    @endif
    <tr>
      <td>Email de connexion</td>
      <td>{{ $user->email }}</td>
    </tr>
  </table>
</div>

<p class="text">Votre mot de passe temporaire est :</p>

<div class="password-box">
  <span>{{ $password }}</span>
</div>

<p class="text">
  Connectez-vous avec votre adresse email et ce mot de passe, puis rendez-vous dans
  <strong>Mon profil</strong> pour changer votre mot de passe et securiser votre compte.
</p>

<hr class="divider">

<p class="text" style="font-size:13px; color:#64748b;">
  <strong>Ce que vous pouvez faire dans votre espace personnel :</strong>
</p>

<p class="text" style="font-size:13px; color:#64748b;">
  Depuis votre espace, vous avez acces a :
</p>
<ul style="font-size:13px; color:#64748b; padding-left:20px; line-height:2;">
  <li>Vos <strong>presences et pointages</strong></li>
  <li>Vos <strong>fiches de paie</strong> (consultation et telechargement PDF)</li>
  <li>La soumission de <strong>justificatifs d'absence</strong> en ligne</li>
  <li>La modification de votre <strong>mot de passe</strong></li>
</ul>
@endsection
