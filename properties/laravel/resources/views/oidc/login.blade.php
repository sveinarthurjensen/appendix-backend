{{-- Innloggingsvalg for /oidc/authorize: Microsoft (faste ansatte) eller BankID (konsulenter, pasienter/parter) --}}
@extends('oidc.layout')
@section('title', 'Logg inn')
@section('content')
  <div class="ic">🔐</div>
  <h1>Logg inn</h1>
  <p>Du er på vei til <strong>{{ $clientName }}</strong>. Velg hvordan du vil identifisere deg.</p>

  <a class="btn btn-ms" href="{{ $entraUrl }}">
    <svg width="18" height="18" viewBox="0 0 21 21" aria-hidden="true"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
    Logg inn med Microsoft
  </a>
  <a class="btn btn-bankid" href="{{ $bankidUrl }}">Logg inn med BankID</a>

  <p class="muted">Ansatte bruker Microsoft-kontoen sin. Konsulenter, pasienter og parter bruker BankID.</p>
  <a class="link" href="mailto:post@aprop.no">Kontakt administrator</a>
@endsection
