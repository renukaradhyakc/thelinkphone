@php
    session(['callalink_deeplink' => $appDeepLink, 'callalink_userid' => $userId]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="apple-itunes-app" content="app-id=6755759920, app-argument=https://app.callalink.com/call/{{ $userId }}">
  <link rel="icon" type="image/png" href="/images/callalink_logo.png">
  <link rel="apple-touch-icon" href="/images/callalink_logo.png">
  <title>Opening CallALink App...</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --grad-start: #4E65FF;
      --grad-end: #72DEFF;
      --card-bg: #ffffff;
      --text-primary: #0F1117;
      --text-secondary: #5A6074;
      --text-muted: #9399AD;
      --border: #E8EAF2;
      --green: #22C55E;
      --green-dark: #16A34A;
      --red-annot: #EF4444;
      --step-bg: #F7F8FC;
      --radius-lg: 20px;
      --radius-md: 12px;
      --radius-sm: 8px;
      --font: 'Poppins', sans-serif;
    }

    body {
      font-family: var(--font);
      background: linear-gradient(180deg, #F7F9FF 0%, #EEF2FF 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    /* Subtle background mesh dots */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image: radial-gradient(circle, rgba(255,255,255,0.08) 1px, transparent 1px);
      background-size: 28px 28px;
      pointer-events: none;
    }

    .card {
      background: var(--card-bg);
      border-radius: var(--radius-lg);
      padding: 36px 32px;
      width: 100%;
      max-width: 440px;
      box-shadow: 0 24px 64px rgba(30, 40, 120, 0.18), 0 2px 8px rgba(0,0,0,0.06);
      position: relative;
      animation: cardIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(24px) scale(0.97); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Top brand strip */
    .brand-strip {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 24px;
    }

    .brand-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      background: #ffffff;
      border: 1px solid var(--border);
      cursor: pointer;
      transition: transform 0.15s, box-shadow 0.15s;
    }

    .brand-icon:hover {
      transform: scale(1.06);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }

    .brand-name {
      font-size: 18px;
      font-weight: 700;
      color: var(--text-primary);
      letter-spacing: -0.3px;
    }

    .brand-sub {
      font-size: 12px;
      color: var(--text-muted);
      font-weight: 400;
    }

    /* Status pill */
    .status-wrap {
      display: flex;
      justify-content: center;
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #FFF7ED;
      color: #C2410C;
      font-size: 12px;
      font-weight: 600;
      padding: 5px 12px;
      border-radius: 999px;
      border: 1px solid #FED7AA;
      margin-bottom: 16px;
    }

    .status-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #F97316;
      animation: blink 1.4s ease-in-out infinite;
    }

    @keyframes blink {
      0%, 100% { opacity: 1; }
      50%       { opacity: 0.3; }
    }

    .card-title {
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      font-weight: 700;
      color: var(--text-primary);
      letter-spacing: -0.4px;
      margin-bottom: 6px;
      line-height: 1.2;
    }

    .card-sub {
      font-size: 14px;
      color: var(--text-secondary);
      margin-bottom: 24px;
      line-height: 1.5;
    }

    /* Primary CTA */
    .cta-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 15px 24px;
      background: var(--green);
      color: #fff;
      font-family: var(--font);
      font-size: 15px;
      font-weight: 700;
      border: none;
      border-radius: 14px;
      cursor: pointer;
      text-decoration: none;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
      box-shadow: 0 4px 16px rgba(34, 197, 94, 0.35);
      margin-bottom: 16px;
    }

    .cta-btn:hover  { background: var(--green-dark); transform: translateY(-1px); box-shadow: 0 6px 22px rgba(34, 197, 94, 0.4); }
    .cta-btn:active { transform: translateY(0); }

    .cta-icon { font-size: 18px; }

    /* Store buttons */
    .store-row {
      display: flex;
      gap: 10px;
      margin-bottom: 24px;
    }

    .store-btn {
      flex: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: #0F1117;
      color: #fff;
      padding: 10px 14px;
      border-radius: var(--radius-sm);
      text-decoration: none;
      transition: opacity 0.2s, transform 0.15s;
    }

    .store-btn:hover { opacity: 0.85; transform: translateY(-1px); }

    .store-btn-text { display: flex; flex-direction: column; align-items: flex-start; line-height: 1.2; }
    .store-label { font-size: 9px; opacity: 0.7; font-weight: 400; }
    .store-name  { font-size: 14px; font-weight: 700; }

    /* Divider */
    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
    }
    .divider-line { flex: 1; height: 1px; background: var(--border); }
    .divider-text { font-size: 11px; font-weight: 600; color: var(--text-muted); letter-spacing: 0.5px; text-transform: uppercase; }

    /* Steps section */
    .steps-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .step-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      background: var(--step-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 12px 14px;
      margin-bottom: 8px;
      transition: border-color 0.2s;
    }

    .step-item:last-child { margin-bottom: 0; }
    .step-item:hover { border-color: #c7cbe0; }

    .step-num {
      width: 22px;
      height: 22px;
      min-width: 22px;
      border-radius: 50%;
      background: var(--red-annot);
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-top: 1px;
    }

    .step-text {
      font-size: 13px;
      color: var(--text-secondary);
      line-height: 1.45;
    }

    .step-text strong {
      color: var(--text-primary);
      font-weight: 600;
    }

    /* User ID */
    .user-id-box {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: var(--step-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 10px 14px;
      margin-top: 20px;
    }

    .user-id-label { font-size: 11px; color: var(--text-muted); font-weight: 500; white-space: nowrap; }
    .user-id-val   { font-size: 13px; font-weight: 600; color: var(--text-primary); font-family: 'Courier New', monospace; word-break: break-all; }

    .footer-note {
      text-align: center;
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 16px;
      line-height: 1.5;
    }
  </style>
</head>
<body>
  <div class="card">

    <!-- Brand -->
    <div class="brand-strip">
      <a href="https://callalink.com" target="_blank" style="text-decoration: none;">
        <div class="brand-icon">
          <img src="/images/callalink_logo.png" alt="CallALink" style="width: 32px; height: 32px; object-fit: contain;">
        </div>
      </a>
      <div>
        <div class="brand-name">CallALink</div>
        <div class="brand-sub">Instant call redirect</div>
      </div>
    </div>

    <!-- Status -->
    <div class="status-wrap">
      <div class="status-pill">
        <span class="status-dot"></span>
        Redirecting you to the app…
      </div>
    </div>

    <!-- Heading -->
    <h1 class="card-title">App didn't open?</h1>
    <p class="card-sub">Already signed up and logged in? Just tap the button below to start the call.</p>

    <!-- Primary CTA -->
    <a href="{{ $appDeepLink }}" class="cta-btn" id="open-btn">
      <span class="cta-icon">📱</span>
      Open CallALink App
    </a>

    <!-- Store buttons -->
    <div class="store-row">
      <a href="https://play.google.com/store/apps/details?id=com.thelinkphone.app" target="_blank" class="store-btn">
        <img src="/images/google-play-icon.svg" alt="Google Play" id="gplay-icon" style="width: 24px; height: 24px; flex-shrink: 0; filter: invert(1) brightness(2); object-fit: contain;">
        <div class="store-btn-text">
          <span class="store-label">Download on the</span>
          <span class="store-name">Google Play</span>
        </div>
      </a>
      <a href="https://apps.apple.com/us/app/callalink/id6755759920" target="_blank" class="store-btn">
        <img src="/images/app-store-icon.svg" alt="App Store" id="appstore-icon" style="width: 24px; height: 24px; flex-shrink: 0; filter: invert(1) brightness(2); object-fit: contain;">
        <div class="store-btn-text">
          <span class="store-label">Download on the</span>
          <span class="store-name">App Store</span>
        </div>
      </a>
    </div>

    <!-- Steps divider -->
    <div class="divider">
      <div class="divider-line"></div>
      <span class="divider-text">How it works</span>
      <div class="divider-line"></div>
    </div>

    <!-- Step-by-step instructions -->
    <div class="steps-title">📋 First time? Follow these steps</div>

    <div class="step-item">
      <div class="step-num">1</div>
      <div class="step-text"><strong>Download the CallALink app</strong> from Google Play or the App Store using the buttons above.</div>
    </div>
    <div class="step-item">
      <div class="step-num">2</div>
      <div class="step-text"><strong>Create your account</strong> - visit <a href="https://callalink.com" target="_blank" style="color: var(--grad-start); font-weight: 600; text-decoration: none;">callalink.com</a> and sign up by filling in your details.</div>
    </div>
    <div class="step-item">
      <div class="step-num">3</div>
      <div class="step-text"><strong>Log in to the app</strong> using the same email &amp; password you used to sign up on the website.</div>
    </div>
    <div class="step-item">
      <div class="step-num">4</div>
      <div class="step-text"><strong>Come back here</strong> and tap <em>"Open CallALink App"</em> above - or scan the QR code shared with you.</div>
    </div>
    <div class="step-item" style="border-color: #bbf7d0; background: #f0fdf4;">
      <div class="step-num" style="background: #22C55E;">✓</div>
      <div class="step-text" style="color: #15803d;"><strong>The call will start automatically</strong> - no extra tapping needed. You're all set! 🎉</div>
    </div>

    <!-- User ID -->
    <div class="user-id-box">
      <span class="user-id-label">User ID :</span>
      <span class="user-id-val">{{ $userId }}</span>
    </div>

    <p class="footer-note">Don't have the app? Install it from your play store or app store first.</p>
    <p class="footer-note" style="margin-top: 8px;">
      New here? <a href="{{ route('how.to.use.page') }}" style="color: var(--grad-start); font-weight: 600; text-decoration: none;">View the full setup guide →</a>
    </p>
  </div>

  <script>
    console.log('CallALink redirect page loaded');
    console.log('User: {{ $userId }}');
    console.log('App deep link: {{ $appDeepLink }}');

    let redirectAttempted = false;
    let fallbackShown = false;

    function attemptAppOpen() {
      if (redirectAttempted) return;
      redirectAttempted = true;
      try {
        window.location.href = "{{ $appDeepLink }}";
      } catch (e) {}
      setTimeout(function () {
        try {
          var iframe = document.createElement('iframe');
          iframe.style.cssText = 'display:none;width:1px;height:1px;';
          iframe.src = "{{ $appDeepLink }}";
          document.body.appendChild(iframe);
          setTimeout(function () { if (iframe.parentNode) iframe.parentNode.removeChild(iframe); }, 2000);
        } catch (e) {}
      }, 500);
      setTimeout(function () {
        try {
          var link = document.createElement('a');
          link.href = "{{ $appDeepLink }}";
          link.style.display = 'none';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        } catch (e) {}
      }, 1000);
    }

    // Retry button feedback
    document.getElementById('open-btn').addEventListener('click', function () {
      this.innerHTML = '🔄 Opening app…';
      setTimeout(() => { this.innerHTML = '<span class="cta-icon">📱</span> Open CallALink App'; }, 2000);
    });

    attemptAppOpen();

    // Icon fallbacks — if /images/ files don't load, swap to inline SVG
    function makeSVG(pathD) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('fill', 'white');
      svg.style.cssText = 'width:24px;height:24px;flex-shrink:0;';
      var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', pathD);
      svg.appendChild(path);
      return svg;
    }
    var gplay = document.getElementById('gplay-icon');
    if (gplay) gplay.onerror = function() {
      this.replaceWith(makeSVG('M3.18 23.76c.37.2.8.22 1.2.06l11.4-6.58-2.54-2.54L3.18 23.76zm16.1-10.3L16.5 11.7 13.7 14.5l2.54 2.54 2.97-1.71c.84-.49.84-1.63.07-2.07zM3 1.24L14.16 12.4 3.18 19.2a1.4 1.4 0 0 1-1.2.07A1.4 1.4 0 0 1 1.2 18V3a1.4 1.4 0 0 1 1.8-1.76zm9.88 9.52L3.18 1.24c.37-.2.83-.17 1.2.06l11.4 6.58-2.9 2.88z'));
    };
    var appstore = document.getElementById('appstore-icon');
    if (appstore) appstore.onerror = function() {
      this.replaceWith(makeSVG('M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98l-.09.06c-.22.15-2.19 1.28-2.17 3.81.03 3.02 2.65 4.03 2.68 4.04l-.06.27zM13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z'));
    };

  </script>
</body>
</html>