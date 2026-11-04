<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $title }}</title>
<style>
  /* Motion where the mail app supports it (Apple Mail, iOS, Outlook.com, many others); static everywhere else. */
  @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
  @keyframes shimmer { 0% { background-position: 0% 50%; } 100% { background-position: 200% 50%; } }
  @keyframes pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(209, 42, 50, .45); } 50% { box-shadow: 0 0 0 6px rgba(209, 42, 50, 0); } }
  @keyframes glow { 0%, 100% { box-shadow: 0 10px 24px rgba(209, 42, 50, .28); } 50% { box-shadow: 0 12px 32px rgba(209, 42, 50, .48); } }
  @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
  body { margin: 0; padding: 0; background: #f4f4f5; -webkit-font-smoothing: antialiased; }
  .card { animation: rise .7s cubic-bezier(.2, .8, .2, 1) both; }
  .hero { background-image: linear-gradient(110deg, #b21f27 0%, #d12a32 35%, #e8545a 50%, #d12a32 65%, #b21f27 100%) !important; background-size: 200% 100% !important; animation: shimmer 6s linear infinite; }
  .mark { animation: float 4s ease-in-out infinite; }
  .dot { animation: pulse 1.8s ease-out infinite; }
  .cta { animation: glow 2.6s ease-in-out infinite; transition: transform .2s ease; }
  .cta:hover { transform: translateY(-2px); }
  .link:hover { text-decoration: underline !important; }
  @media (prefers-reduced-motion: reduce) { .card, .hero, .mark, .dot, .cta { animation: none !important; } }
  @media (max-width: 620px) { .shell { width: 100% !important; } .pad { padding-left: 24px !important; padding-right: 24px !important; } .title { font-size: 22px !important; } }
  @media (prefers-color-scheme: dark) {
    body, .bg { background: #08080a !important; }
    .card { background: #18181b !important; border-color: #2a2a30 !important; }
    .ink { color: #f4f4f5 !important; }
    .muted { color: #a1a1aa !important; }
    .pill { background: #3a1c1e !important; color: #f4787d !important; }
    .divider { border-color: #2a2a30 !important; }
  }
</style>
</head>
<body class="bg" style="margin:0;padding:0;background:#f4f4f5;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ \Illuminate\Support\Str::limit($body, 110) }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg" style="background:#f4f4f5;">
  <tr>
    <td align="center" style="padding:32px 12px;">
      <table role="presentation" class="shell" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">
        <tr>
          <td class="card" style="background:#ffffff;border:1px solid #e4e4e7;border-radius:20px;overflow:hidden;box-shadow:0 16px 42px rgba(20,20,25,.10);">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td class="hero pad" style="background:#d12a32;background-image:linear-gradient(120deg,#b21f27,#d12a32);padding:28px 40px;border-radius:20px 20px 0 0;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td class="mark" style="width:42px;height:42px;background:#ffffff;border-radius:12px;text-align:center;vertical-align:middle;font:800 13px/42px -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#d12a32;letter-spacing:-.02em;">QIU</td>
                      <td style="padding-left:14px;font:800 18px/1.2 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#ffffff;letter-spacing:-.02em;">
                        Campus Hub
                        <div style="font:600 11px/1.6 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:rgba(255,255,255,.82);letter-spacing:.08em;text-transform:uppercase;">Student Life Division</div>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td class="pad" style="padding:36px 40px 8px 40px;">
                  <span class="pill" style="display:inline-block;background:#fbe9ea;color:#b21f27;border-radius:999px;padding:6px 12px;font:700 11px/1 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;letter-spacing:.06em;text-transform:uppercase;">
                    <span class="dot" style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#d12a32;margin-right:6px;vertical-align:1px;"></span>{{ $kind }}
                  </span>
                  <p class="muted" style="margin:22px 0 6px;font:400 15px/1.6 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#6b6b72;">Hi {{ $name }},</p>
                  <h1 class="title ink" style="margin:0 0 14px;font:800 26px/1.25 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#17171a;letter-spacing:-.025em;">{{ $title }}</h1>
                  <p class="ink" style="margin:0;font:400 16px/1.65 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#3f3f46;">{{ $body }}</p>
                </td>
              </tr>
              <tr>
                <td class="pad" style="padding:28px 40px 8px 40px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td class="cta" style="border-radius:12px;background:#d12a32;background-image:linear-gradient(135deg,#d12a32,#b21f27);box-shadow:0 10px 24px rgba(209,42,50,.28);">
                        <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:15px 28px;font:700 15px/1 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#ffffff;text-decoration:none;border-radius:12px;">{{ $action }} &rarr;</a>
                      </td>
                    </tr>
                  </table>
                  <p class="muted" style="margin:16px 0 0;font:400 12px/1.6 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#6b6b72;">
                    Button not working? Open this link:<br>
                    <a class="link" href="{{ $url }}" style="color:#b21f27;text-decoration:none;word-break:break-all;">{{ $url }}</a>
                  </p>
                </td>
              </tr>
              <tr>
                <td class="pad" style="padding:24px 40px 32px 40px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td class="divider" style="border-top:1px solid #e4e4e7;padding-top:18px;font:600 13px/1.6 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                        <a class="link" href="{{ $notificationsUrl }}" style="color:#17171a;text-decoration:none;" target="_blank"><span class="ink">See all your notifications</span></a>
                        <span class="muted" style="color:#d4d4d8;padding:0 8px;">&bull;</span>
                        <a class="link" href="{{ $settingsUrl }}" style="color:#17171a;text-decoration:none;" target="_blank"><span class="ink">Notification settings</span></a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:22px 24px 0;text-align:center;font:400 12px/1.7 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#8a8a93;" class="muted">
            @if ($mutable)
              You receive this because this type of notification is switched on. Switch it off in Notification settings and it stops by email too.
            @else
              Approvals, payments and reminders are always sent so nothing important is missed.
            @endif
            <br>QIU Campus Hub &middot; Student Life Division, Quest International University, Ipoh, Perak, Malaysia
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
