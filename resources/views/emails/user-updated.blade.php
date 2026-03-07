@extends('emails.layout')

@section('content')
<p class="greeting">Bonjour, {{ $user->first_name }} {{ $user->last_name }},</p>

<p class="text">
  Les informations de votre compte sur la plateforme <strong>Core Tanga Group</strong>
  ont ete mises a jour. Voici le recap de votre profil actuel :
</p>

<div class="info-box">
  <table>
    <tr>
      <td>Prenom</td>
      <td>{{ $user->first_name }}</td>
    </tr>
    <tr>
      <td>Nom</td>
      <td>{{ $user->last_name }}</td>
    </tr>
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
    <tr>
      <td>Statut</td>
      <td>{{ $user->is_active ? 'Actif' : 'Inactif' }}</td>
    </tr>
  </table>
</div>

<p class="text">
  Si vous n'etes pas a l'origine de cette modification ou si ces informations vous semblent incorrectes,
  veuillez contacter votre administrateur immediatement.
</p>
@endsection
