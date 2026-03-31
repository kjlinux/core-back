@extends('emails.layout')

@section('content')
<p class="greeting">Bienvenue, {{ $user->first_name }} {{ $user->last_name }} !</p>

<p class="text">
  Votre compte a ete cree avec succes sur la plateforme <strong>Core Tanga Group</strong>.
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
  Pour des raisons de securite, nous vous recommandons de changer votre mot de passe
  des votre premiere connexion.
</p>

<hr class="divider">

<p class="text" style="font-size:13px; color:#64748b;">
  <strong>Ce que vous pouvez faire dans l'application selon votre role :</strong>
</p>

@if($user->role === 'super_admin')
<p class="text" style="font-size:13px; color:#64748b;">
  En tant que <strong>Super Administrateur</strong>, vous avez un acces complet a toutes les fonctionnalites :
  gestion des entreprises, des utilisateurs, des modules Pointage RFID, Biometrique, Feelback et Marketplace.
</p>
@elseif($user->role === 'admin_enterprise')
<p class="text" style="font-size:13px; color:#64748b;">
  En tant qu'<strong>Administrateur Entreprise</strong>{{ $user->company ? ' de ' . $user->company->name : '' }},
  vous gerez les sites, departements, employes, dispositifs et rapports de votre entreprise sur les modules
  Pointage RFID, Biometrique, Feelback et Marketplace.
</p>
@elseif($user->role === 'technicien')
<p class="text" style="font-size:13px; color:#64748b;">
  En tant que <strong>Technicien</strong>{{ $user->company ? ' chez ' . $user->company->name : '' }},
  vous etes charge de la mise en service des comptes clients : creation des entreprises, sites,
  departements, employes, attribution des cartes RFID, enrolements biometriques, QR codes et
  mises a jour firmware des terminaux.
</p>
@else
<p class="text" style="font-size:13px; color:#64748b;">
  En tant que <strong>Manager</strong>{{ $user->company ? ' chez ' . $user->company->name : '' }},
  vous avez acces aux tableaux de bord de pointage, aux rapports de presence et aux statistiques
  de votre departement.
</p>
@endif
@endsection
