{{-- Feilside for innlogging/OIDC. $back = klientens redirect_uri med error (valgfri) --}}
@extends('oidc.layout')
@section('title', $title)
@section('content')
  <div class="ic">⚠️</div>
  <h1>{{ $title }}</h1>
  <p>{{ $message }}</p>
  @if(!empty($detail))
    <div class="det">{{ $detail }}</div>
  @endif
  @if(!empty($back))
    <a class="btn btn-plain" href="{{ $back }}">Tilbake til appen</a>
  @else
    <a class="btn btn-plain" href="{{ rtrim(config('services.oidc.issuer') ?: config('services.portal.issuer') ?: '/', '/') }}/">Tilbake til forsiden</a>
  @endif
  <br><a class="link" href="mailto:post@aprop.no">Kontakt administrator</a>
@endsection
