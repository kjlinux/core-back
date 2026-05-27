@extends('emails.layout')

@section('content')
<p class="greeting">Bienvenue, {{ $user->first_name }} {{ $user->last_name }} !</p>

<p class="text">
  Votre compte a été créé avec succès sur la plateforme <strong>TangaFlow</strong>.
  Voici vos informations de connexion :
</p>

<div class="info-box">
  <table>
    <tr>
      <td>Email</td>
      <td>{{ $user->email }}</td>
    </tr>
    <tr>
      <td>Role</td>
      <td>
        @if($user->role === 'super_admin')
          <span class="badge badge-super">Super Administrateur</span>
        @elseif($user->role === 'admin_enterprise')
          <span class="badge badge-admin">Administrateur Entreprise</span>
        @elseif($user->role === 'technicien')
          <span class="badge badge-admin">Technicien</span>
        @else
          <span class="badge badge-manager">Manager</span>
        @endif
      </td>
    </tr>
    @if($user->company)
    <tr>
      <td>Entreprise</td>
      <td>{{ $user->company->name }}</td>
    </tr>
    @endif
  </table>
</div>

<p class="text">Votre mot de passe temporaire est :</p>

<div class="password-box">
  <span>{{ $password }}</span>
</div>

<p class="text">
  Pour des raisons de sécurité, nous vous recommandons de changer votre mot de passe
  dès votre première connexion.
</p>

<hr class="divider">

<p class="text" style="font-size:13px; color:#64748b;">
  <strong>Ce que vous pouvez faire dans l'application selon votre role :</strong>
</p>

@if($user->role === 'super_admin')
<p class="text" style="font-size:13px; color:#64748b;">
  En tant que <strong>Super Administrateur</strong>, vous avez un accès complet à toutes les fonctionnalités :
  gestion des entreprises, des utilisateurs, des modules Pointage RFID, Biométrique, Feelback et Marketplace.
</p>
@elseif($user->role === 'admin_enterprise')
<p class="text" style="font-size:13px; color:#64748b;">
  En tant qu'<strong>Administrateur Entreprise</strong>{{ $user->company ? ' de ' . $user->company->name : '' }},
  vous gérez les sites, départements, employés, dispositifs et rapports de votre entreprise sur les modules
  Pointage RFID, Biométrique, Feelback et Marketplace.
</p>
@elseif($user->role === 'technicien')
<p class="text" style="font-size:13px; color:#64748b;">
  En tant que <strong>Technicien</strong>{{ $user->company ? ' chez ' . $user->company->name : '' }},
  vous êtes chargé de la mise en service des comptes clients : création des entreprises, sites,
  départements, employés, attribution des cartes RFID, enrôlements biométriques, QR codes et
  mises à jour firmware des terminaux.
</p>
@else
<p class="text" style="font-size:13px; color:#64748b;">
  En tant que <strong>Manager</strong>{{ $user->company ? ' chez ' . $user->company->name : '' }},
  vous avez accès aux tableaux de bord de pointage, aux rapports de présence et aux statistiques
  de votre département.
</p>
@endif
@endsection
