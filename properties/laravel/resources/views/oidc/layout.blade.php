{{-- Felles ramme for innloggings- og feilsidene til OIDC-utstederen (samme utseende som Base44-feilsidene) --}}
<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>@yield('title') – Appendix Properties</title>
<style>
  body{margin:0;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f8fafc;color:#0f172a}
  .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
  .card{max-width:28rem;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:1rem;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:2rem;text-align:center}
  .ic{width:3.5rem;height:3.5rem;border-radius:.9rem;background:#1e3a5f;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 1.1rem;font-size:1.6rem}
  h1{font-size:1.25rem;margin:0 0 .5rem}
  p{color:#475569;font-size:.95rem;line-height:1.5;margin:.35rem 0}
  .det{background:#f1f5f9;border-radius:.6rem;padding:.6rem .8rem;font-size:.8rem;color:#64748b;margin-top:.8rem;word-break:break-word}
  .btn{display:flex;align-items:center;justify-content:center;gap:.6rem;width:100%;box-sizing:border-box;margin-top:.8rem;padding:.8rem 1.2rem;border-radius:.6rem;text-decoration:none;font-weight:600;font-size:.95rem;border:1px solid transparent}
  .btn-ms{background:#1e3a5f;color:#fff}
  .btn-bankid{background:#fff;color:#1e3a5f;border-color:#1e3a5f}
  .btn-plain{display:inline-block;width:auto;margin-top:1.2rem;padding:.6rem 1.2rem;background:#1e3a5f;color:#fff}
  a.link{display:inline-block;margin-top:.8rem;color:#64748b;font-size:.85rem;text-decoration:underline}
  .muted{font-size:.8rem;color:#94a3b8;margin-top:1.2rem}
</style>
</head>
<body>
<div class="wrap"><div class="card">
@yield('content')
</div></div>
</body>
</html>
