@extends('emails.layout')

@section('content')
<p class="greeting">Mise a jour firmware disponible</p>

<p class="text">
  Une nouvelle version du firmware est disponible pour vos capteurs.
  Connectez-vous a la plateforme pour effectuer la mise a jour de vos terminaux.
</p>

<div class="info-box">
  <table>
    <tr>
      <td>Version</td>
      <td>
        <span class="badge" style="background:#dcfce7;color:#166534;">v{{ $firmware->version }}</span>
      </td>
    </tr>
    <tr>
      <td>Type de capteur</td>
      <td>
        @if($firmware->device_kind === 'rfid')
          <span class="badge badge-admin">Pointage RFID</span>
        @else
          <span class="badge badge-manager">Biometrique</span>
        @endif
      </td>
    </tr>
    <tr>
      <td>Publie le</td>
      <td>{{ $firmware->published_at?->format('d/m/Y \a H:i') }}</td>
    </tr>
    @if($firmware->description)
    <tr>
      <td>Description</td>
      <td>{{ $firmware->description }}</td>
    </tr>
    @endif
  </table>
</div>

<p class="text">
  Pour mettre a jour vos capteurs, connectez-vous a la plateforme.
  Un bandeau de notification vous guidera directement vers la mise a jour.
</p>

<div style="text-align:center;">
  <a href="{{ config('app.url') }}" class="btn">Se connecter et mettre a jour</a>
</div>

<hr class="divider">

<p class="text" style="font-size:13px;color:#64748b;">
  La mise a jour est appliquee automatiquement a tous vos capteurs connectes.
  Vous pouvez suivre la progression en temps reel depuis la plateforme.
</p>
@endsection
