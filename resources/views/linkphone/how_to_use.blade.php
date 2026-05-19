<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>How to Use - CallALink</title>
  <link rel="icon" type="image/png" href="/images/callalink_logo.png">
  <link rel="apple-touch-icon" href="/images/callalink_logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /* ── DARK THEME (default) ── */
    :root,
    [data-theme="dark"] {
      --bg:        #0A0C14;
      --bg2:       #10131F;
      --bg3:       #161926;
      --surface:   #1C2033;
      --accent:    #4E65FF;
      --accent2:   #72DEFF;
      --green:     #22C55E;
      --text:      #F0F2FF;
      --muted:     #6B7194;
      --border:    rgba(255,255,255,0.07);
      --nav-bg:    rgba(10,12,20,0.85);
      --phone-bg:  #1a1d2e;
      --phone-notch: #000000;
      --toggle-bg: #1C2033;
      --font-head: 'Poppins', sans-serif;
      --font-body: 'Poppins', sans-serif;
      --step1: #4E65FF;
      --step2: #A855F7;
      --step3: #F59E0B;
      --step4: #22C55E;
      --step5: #F43F5E;
    }

    /* ── LIGHT THEME ── */
    [data-theme="light"] {
      --bg:        #F8F9FF;
      --bg2:       #FFFFFF;
      --bg3:       #EEF1FF;
      --surface:   #E8EBF8;
      --accent:    #4E65FF;
      --accent2:   #2D7DD2;
      --green:     #16A34A;
      --text:      #0F1117;
      --muted:     #5A6074;
      --border:    rgba(0,0,0,0.08);
      --nav-bg:    rgba(248,249,255,0.9);
      --phone-bg:  #E8EBF8;
      --phone-notch: #000000;
      --toggle-bg: #E8EBF8;
    }

    html { scroll-behavior: smooth; }

    body {
      font-family: var(--font-body);
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      overflow-x: hidden;
      transition: background 0.3s, color 0.3s;
    }

    /* ── NAV ── */
    nav {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 40px;
      background: var(--nav-bg);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
      transition: background 0.3s, border-color 0.3s;
    }

    .nav-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
    }

    .nav-logo {
      width: 36px; height: 36px;
      border-radius: 10px;
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border: 1px solid var(--border);
    }

    .nav-logo img { width: 28px; height: 28px; object-fit: contain; }

    .nav-name {
      font-family: var(--font-head);
      font-size: 18px;
      font-weight: 700;
      color: var(--text);
      letter-spacing: -0.3px;
      transition: color 0.3s;
    }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* ── THEME TOGGLE ── */
    .theme-toggle {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: rgba(255,255,255,0.1);
      border: 1.5px solid rgba(255,255,255,0.25);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.3s, border-color 0.3s, transform 0.15s;
      flex-shrink: 0;
    }
    .theme-toggle:hover {
      background: rgba(255,255,255,0.18);
      border-color: rgba(255,255,255,0.4);
      transform: scale(1.08);
    }
    [data-theme="light"] .theme-toggle {
      background: rgba(0,0,0,0.07);
      border-color: rgba(0,0,0,0.15);
    }
    [data-theme="light"] .theme-toggle:hover {
      background: rgba(0,0,0,0.12);
      border-color: rgba(0,0,0,0.25);
    }

    /* show moon in dark, sun in light */
    .theme-toggle .icon-sun  { display: none; }
    .theme-toggle .icon-moon { display: block; filter: brightness(0) invert(1); }
    [data-theme="light"] .theme-toggle .icon-sun  { display: block; filter: brightness(0); }
    [data-theme="light"] .theme-toggle .icon-moon { display: none; }

    .nav-cta {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--accent);
      color: #fff;
      font-family: var(--font-body);
      font-size: 13px;
      font-weight: 500;
      padding: 9px 18px;
      border-radius: 999px;
      text-decoration: none;
      transition: opacity 0.2s, transform 0.15s;
    }
    .nav-cta:hover { opacity: 0.85; transform: translateY(-1px); }

    /* ── HERO ── */
    .hero {
      padding: 160px 40px 80px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 60px; left: 50%;
      transform: translateX(-50%);
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(78,101,255,0.1) 0%, transparent 70%);
      pointer-events: none;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(78,101,255,0.1);
      border: 1px solid rgba(78,101,255,0.3);
      color: #818cf8;
      font-size: 12px;
      font-weight: 500;
      padding: 5px 14px;
      border-radius: 999px;
      margin-bottom: 24px;
      letter-spacing: 0.4px;
    }

    .hero-title {
      font-family: var(--font-head);
      font-size: clamp(36px, 6vw, 64px);
      font-weight: 700;
      line-height: 1.15;
      letter-spacing: -0.5px;
      margin-bottom: 20px;
      color: var(--text);
      transition: color 0.3s;
    }

    .hero-title span {
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .hero-sub {
      font-size: 17px;
      color: var(--muted);
      max-width: 480px;
      margin: 0 auto 48px;
      line-height: 1.6;
      transition: color 0.3s;
    }

    .hero-steps-count {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-size: 13px;
      color: var(--muted);
    }

    .step-dot-row { display: flex; gap: 6px; }
    .step-dot-row span { width: 8px; height: 8px; border-radius: 50%; }
    .step-dot-row span:nth-child(1) { background: var(--step1); }
    .step-dot-row span:nth-child(2) { background: var(--step2); }
    .step-dot-row span:nth-child(3) { background: var(--step3); }
    .step-dot-row span:nth-child(4) { background: var(--step4); }
    .step-dot-row span:nth-child(5) { background: var(--step5); }

    /* ── PROGRESS SIDEBAR ── */
    .progress-sidebar {
      position: fixed;
      right: 28px; top: 50%;
      transform: translateY(-50%);
      display: flex;
      flex-direction: column;
      gap: 10px;
      z-index: 50;
    }

    .prog-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--surface);
      border: 1.5px solid var(--muted);
      cursor: pointer;
      transition: all 0.3s;
    }

    .prog-dot.active {
      width: 10px; height: 10px;
      border-color: var(--text);
      background: var(--text);
    }

    /* ── STEPS ── */
    .steps-wrapper { padding-bottom: 80px; }

    .step-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      min-height: 100vh;
      align-items: center;
      position: relative;
      overflow: hidden;
      transition: background 0.3s;
    }

    .step-section:nth-child(even) { direction: rtl; }
    .step-section:nth-child(even) > * { direction: ltr; }

    .step-section:nth-child(1) { background: var(--bg); }
    .step-section:nth-child(2) { background: var(--bg2); }
    .step-section:nth-child(3) { background: var(--bg); }
    .step-section:nth-child(4) { background: var(--bg2); }
    .step-section:nth-child(5) { background: var(--bg); }

    .step-section::before {
      content: attr(data-num);
      position: absolute;
      font-family: var(--font-head);
      font-size: clamp(180px, 25vw, 320px);
      font-weight: 800;
      opacity: 0.04;
      color: var(--text);
      right: -20px; bottom: -40px;
      line-height: 1;
      pointer-events: none;
      user-select: none;
      transition: color 0.3s;
    }

    .step-section:nth-child(even)::before { right: auto; left: -20px; }

    .step-section::after {
      content: '';
      position: absolute;
      top: 0; left: 40px; right: 40px;
      height: 1px;
      background: var(--border);
      transition: background 0.3s;
    }

    .step-content { padding: 80px 60px 80px 80px; }
    .step-section:nth-child(even) .step-content { padding: 80px 80px 80px 60px; }

    .step-tag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 11px;
      font-weight: 500;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      margin-bottom: 20px;
    }

    .step-tag-dot { width: 6px; height: 6px; border-radius: 50%; }

    .step-number {
      font-family: var(--font-head);
      font-size: clamp(52px, 7vw, 88px);
      font-weight: 800;
      line-height: 1;
      margin-bottom: 20px;
      letter-spacing: -1px;
    }

    .step-heading {
      font-family: var(--font-head);
      font-size: clamp(22px, 3vw, 30px);
      font-weight: 700;
      line-height: 1.3;
      margin-bottom: 16px;
      color: var(--text);
      transition: color 0.3s;
    }

    .step-body {
      font-size: 15px;
      color: var(--muted);
      line-height: 1.7;
      max-width: 400px;
      margin-bottom: 28px;
      transition: color 0.3s;
    }

    .step-body strong { color: var(--text); font-weight: 500; }

    .step-body a {
      color: var(--accent2);
      text-decoration: none;
      border-bottom: 1px solid rgba(78,101,255,0.3);
      transition: border-color 0.2s;
    }
    .step-body a:hover { border-color: var(--accent2); }

    /* store icon in step 1 action buttons — white in dark, colored in light */
    .step-action-icon { filter: invert(1); }
    [data-theme="light"] .step-action-icon { filter: none; }

    .step-action {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      font-weight: 500;
      padding: 10px 20px;
      border-radius: 999px;
      text-decoration: none;
      border: 1.5px solid;
      transition: background 0.2s, color 0.2s;
    }

    /* ── PHONE FRAME ── */
    .step-visual {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 60px 40px;
      position: relative;
    }

    .phone-frame {
      position: relative;
      width: 240px;
      border-radius: 36px;
      background: var(--phone-bg);
      border: 2px solid var(--border);
      box-shadow: 0 0 0 1px rgba(255,255,255,0.04), 0 40px 80px rgba(0,0,0,0.2), 0 0 60px rgba(78,101,255,0.08);
      overflow: hidden;
      aspect-ratio: 9/19;
      transition: background 0.3s, border-color 0.3s;
    }

    .phone-frame::before {
      content: '';
      position: absolute;
      top: 10px; left: 50%;
      transform: translateX(-50%);
      width: 70px; height: 20px;
      background: var(--phone-notch);
      border-radius: 999px;
      z-index: 10;
      transition: background 0.3s;
    }

    .phone-screen { width: 100%; height: 100%; object-fit: cover; display: block; }

    .phone-glow {
      position: absolute;
      width: 300px; height: 300px;
      border-radius: 50%;
      opacity: 0.12;
      filter: blur(60px);
      pointer-events: none;
    }

    /* ── FINAL STEP ── */
    .final-step {
      text-align: center;
      padding: 120px 40px;
      background: var(--bg3);
      position: relative;
      overflow: hidden;
      transition: background 0.3s;
    }

    .final-step::before {
      content: '';
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%,-50%);
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(34,197,94,0.08) 0%, transparent 70%);
      pointer-events: none;
    }

    .check-circle {
      width: 72px; height: 72px;
      border-radius: 50%;
      background: rgba(34,197,94,0.12);
      border: 2px solid rgba(34,197,94,0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      margin: 0 auto 28px;
    }

    .final-title {
      font-family: var(--font-head);
      font-size: clamp(30px, 5vw, 52px);
      font-weight: 700;
      letter-spacing: -0.5px;
      margin-bottom: 16px;
      color: var(--text);
      transition: color 0.3s;
    }

    .final-sub {
      font-size: 16px;
      color: var(--muted);
      max-width: 420px;
      margin: 0 auto 40px;
      line-height: 1.6;
    }

    .final-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--green);
      color: #fff;
      font-family: var(--font-body);
      font-size: 15px;
      font-weight: 500;
      padding: 14px 32px;
      border-radius: 999px;
      text-decoration: none;
      transition: opacity 0.2s, transform 0.15s;
      box-shadow: 0 4px 24px rgba(34,197,94,0.25);
    }
    .final-btn:hover { opacity: 0.9; transform: translateY(-2px); }

    /* ── FOOTER ── */
    footer {
      padding: 32px 40px;
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 13px;
      color: var(--muted);
      flex-wrap: wrap;
      gap: 12px;
      transition: border-color 0.3s, color 0.3s;
    }

    footer a { color: var(--muted); text-decoration: none; transition: color 0.2s; }
    footer a:hover { color: var(--text); }

    /* ── SCROLL ANIMATIONS ── */
    .reveal {
      opacity: 0; transform: translateY(32px);
      transition: opacity 0.7s cubic-bezier(0.16,1,0.3,1), transform 0.7s cubic-bezier(0.16,1,0.3,1);
    }
    .reveal.visible { opacity: 1; transform: translateY(0); }

    .reveal-right {
      opacity: 0; transform: translateX(40px);
      transition: opacity 0.7s cubic-bezier(0.16,1,0.3,1), transform 0.7s cubic-bezier(0.16,1,0.3,1);
    }
    .reveal-right.visible { opacity: 1; transform: translateX(0); }

    .reveal-left {
      opacity: 0; transform: translateX(-40px);
      transition: opacity 0.7s cubic-bezier(0.16,1,0.3,1), transform 0.7s cubic-bezier(0.16,1,0.3,1);
    }
    .reveal-left.visible { opacity: 1; transform: translateX(0); }

    /* ── RESPONSIVE ── */
    @media (max-width: 768px) {
      nav { padding: 16px 20px; }
      .hero { padding: 120px 24px 60px; }
      .progress-sidebar { display: none; }
      .step-section { grid-template-columns: 1fr; min-height: auto; }
      .step-section:nth-child(even) { direction: ltr; }
      .step-content { padding: 48px 24px 24px; }
      .step-section:nth-child(even) .step-content { padding: 48px 24px 24px; }
      .step-visual { padding: 24px 24px 48px; }
      .phone-frame { width: 180px; }
      footer { padding: 24px 20px; }
    }
  </style>
</head>
<body>

  <!-- NAV -->
  <nav>
    <a href="https://callalink.com" class="nav-brand">
      <div class="nav-logo">
        <img src="/images/callalink_logo.png" alt="CallALink">
      </div>
      <span class="nav-name">CallALink</span>
    </a>
    <div class="nav-right">
      <button class="theme-toggle" id="theme-toggle" aria-label="Toggle theme">
        <img class="icon-moon" src="/images/dark-mode.png" alt="Dark mode" style="width:20px;height:20px;object-fit:contain;">
        <img class="icon-sun" src="/images/light-mode.png" alt="Light mode" style="width:20px;height:20px;object-fit:contain;">
      </button>
      <a href="{{ $appDeepLink ?? '#' }}" class="nav-cta">Open the App →</a>
    </div>
  </nav>

  <!-- HERO -->
  <section class="hero">
    <div class="hero-badge reveal">✦ Setup guide</div>
    <h1 class="hero-title reveal">Get started with<br><span>CallALink</span></h1>
    <p class="hero-sub reveal">Follow these 5 simple steps and you'll be making calls in minutes.</p>
    <div class="hero-steps-count reveal">
      <div class="step-dot-row">
        <span></span><span></span><span></span><span></span><span></span>
      </div>
      5 steps · ~5 minutes
    </div>
  </section>

  <!-- PROGRESS SIDEBAR -->
  <div class="progress-sidebar" id="progress-sidebar">
    <div class="prog-dot active" data-step="1" onclick="scrollToStep(1)"></div>
    <div class="prog-dot" data-step="2" onclick="scrollToStep(2)"></div>
    <div class="prog-dot" data-step="3" onclick="scrollToStep(3)"></div>
    <div class="prog-dot" data-step="4" onclick="scrollToStep(4)"></div>
    <div class="prog-dot" data-step="5" onclick="scrollToStep(5)"></div>
  </div>

  <!-- STEPS -->
  <div class="steps-wrapper">

    <!-- STEP 1 -->
    <section class="step-section" data-num="01" id="step-1">
      <div class="step-content">
        <div class="step-tag reveal" style="color: var(--step1);">
          <span class="step-tag-dot" style="background: var(--step1);"></span>
          Step one
        </div>
        <div class="step-number reveal" style="color: var(--step1);">01</div>
        <h2 class="step-heading reveal">Download the<br>CallALink app</h2>
        <p class="step-body reveal">
          Grab the app from your store of choice. It's available on both
          <strong>Android</strong> and <strong>iOS</strong> - completely free to download.
        </p>
        <div class="reveal" style="display:flex; gap: 10px; flex-wrap: wrap;">
          <a href="https://play.google.com/store/apps/details?id=com.thelinkphone.app" target="_blank" class="step-action" style="color: var(--step1); border-color: rgba(78,101,255,0.4);">
            <img src="/images/google-play-icon.svg" alt="" class="step-action-icon" style="width:16px;height:16px;">
            Google Play
          </a>
          <a href="https://apps.apple.com/us/app/callalink/id6755759920" target="_blank" class="step-action" style="color: var(--step1); border-color: rgba(78,101,255,0.4);">
            <img src="/images/app-store-icon.svg" alt="" class="step-action-icon" style="width:16px;height:16px;">
            App Store
          </a>
        </div>
      </div>
      <div class="step-visual">
        <div class="phone-glow" style="background: var(--step1);"></div>
        <div class="phone-frame reveal-right">
          <img src="/images/how_to_use-1.jpg" alt="Download the app" class="phone-screen">
        </div>
      </div>
    </section>

    <!-- STEP 2 -->
    <section class="step-section" data-num="02" id="step-2">
      <div class="step-content">
        <div class="step-tag reveal" style="color: #c084fc;">
          <span class="step-tag-dot" style="background: var(--step2);"></span>
          Step two
        </div>
        <div class="step-number reveal" style="color: var(--step2);">02</div>
        <h2 class="step-heading reveal">Create your<br>account</h2>
        <p class="step-body reveal">
          Visit <a href="https://callalink.com" target="_blank">callalink.com</a> on your browser
          and sign up. Fill in your details to create your free account -
          takes less than <strong>60 seconds</strong>.
        </p>
        <a href="https://app.callalink.com/sign-up" target="_blank" class="step-action reveal" style="color: #c084fc; border-color: rgba(168,85,247,0.4);">
          Sign up at callalink.com →
        </a>
      </div>
      <div class="step-visual">
        <div class="phone-glow" style="background: var(--step2);"></div>
        <div class="phone-frame reveal-left">
          <img src="/images/how_to_use-2.jpg" alt="Create your account" class="phone-screen">
        </div>
      </div>
    </section>

    <!-- STEP 3 -->
    <section class="step-section" data-num="03" id="step-3">
      <div class="step-content">
        <div class="step-tag reveal" style="color: #fbbf24;">
          <span class="step-tag-dot" style="background: var(--step3);"></span>
          Step three
        </div>
        <div class="step-number reveal" style="color: var(--step3);">03</div>
        <h2 class="step-heading reveal">Log in to<br>the app</h2>
        <p class="step-body reveal">
          Open CallALink on your phone and log in using the
          <strong>same email and password</strong> you used when signing up on the website.
        </p>
      </div>
      <div class="step-visual">
        <div class="phone-glow" style="background: var(--step3);"></div>
        <div class="phone-frame reveal-right">
          <img src="/images/how_to_use-3.jpg" alt="Log in to the app" class="phone-screen">
        </div>
      </div>
    </section>

    <!-- STEP 4 -->
    <section class="step-section" data-num="04" id="step-4">
      <div class="step-content">
        <div class="step-tag reveal" style="color: #4ade80;">
          <span class="step-tag-dot" style="background: var(--step4);"></span>
          Step four
        </div>
        <div class="step-number reveal" style="color: var(--step4);">04</div>
        <h2 class="step-heading reveal">Tap the link or<br>scan the QR code</h2>
        <p class="step-body reveal">
          Come back to the link shared with you or scan the QR code.
          The app will open and <strong>the call starts automatically</strong> -
          no extra steps needed.
        </p>
      </div>
      <div class="step-visual">
        <div class="phone-glow" style="background: var(--step4);"></div>
        <div class="phone-frame reveal-left">
          <img src="/images/how_to_use-4.jpg" alt="Tap link or scan QR" class="phone-screen">
        </div>
      </div>
    </section>

    <!-- STEP 5 -->
    <section class="step-section" data-num="05" id="step-5">
      <div class="step-content">
        <div class="step-tag reveal" style="color: #fb7185;">
          <span class="step-tag-dot" style="background: var(--step5);"></span>
          Step five
        </div>
        <div class="step-number reveal" style="color: var(--step5);">05</div>
        <h2 class="step-heading reveal">Share your QR or<br>link — not your number</h2>
        <p class="step-body reveal">
          Inside the app, go to <strong>My QR Code</strong>. Share your unique QR code
          or your CallALink link with anyone — they can call you directly through the app
          <strong>without ever seeing your real phone number</strong>.
        </p>
      </div>
      <div class="step-visual">
        <div class="phone-glow" style="background: var(--step5);"></div>
        <div class="phone-frame reveal-right">
          <img src="/images/how_to_use-5.jpg" alt="Share your QR code" class="phone-screen">
        </div>
      </div>
    </section>

  </div>

  <!-- FINAL CTA -->
  <section class="final-step">
    <div class="check-circle reveal">✓</div>
    <h2 class="final-title reveal">You're all set! 🎉</h2>
    <p class="final-sub reveal">
      Already done all this? Go back and tap the button - your call will start automatically.
    </p>
    <a href="{{ $appDeepLink ?? '#' }}" class="final-btn reveal">
      📱 Open CallALink &amp; Call Now
    </a>
  </section>

  <!-- FOOTER -->
  <footer>
    <span>© {{ date('Y') }} CallALink. All rights reserved.</span>
    <div style="display:flex; gap: 20px;">
      <a href="https://callalink.com">Home</a>
      <a href="https://www.termsfeed.com/live/dc6768be-8856-4124-a689-3aac885707ae">Privacy</a>
      <a href="https://callalink.com/contact-us/">Contact</a>
    </div>
  </footer>

  <script>
    // ── THEME TOGGLE ──
    const html = document.documentElement;
    const toggleBtn = document.getElementById('theme-toggle');

    // Load saved preference, fallback to dark
    const saved = localStorage.getItem('callalink-theme') || 'dark';
    html.setAttribute('data-theme', saved);

    toggleBtn.addEventListener('click', () => {
      const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      localStorage.setItem('callalink-theme', next);
    });

    // ── SCROLL REVEAL ──
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(el => {
        if (el.isIntersecting) el.target.classList.add('visible');
      });
    }, { threshold: 0.15 });

    document.querySelectorAll('.reveal, .reveal-right, .reveal-left').forEach(el => {
      observer.observe(el);
    });

    // ── PROGRESS SIDEBAR ──
    const sections = document.querySelectorAll('.step-section');
    const dots = document.querySelectorAll('.prog-dot');

    const stepObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const step = entry.target.id.replace('step-', '');
          dots.forEach(d => d.classList.remove('active'));
          const active = document.querySelector(`.prog-dot[data-step="${step}"]`);
          if (active) active.classList.add('active');
        }
      });
    }, { threshold: 0.5 });

    sections.forEach(s => stepObserver.observe(s));

    function scrollToStep(n) {
      document.getElementById('step-' + n).scrollIntoView({ behavior: 'smooth' });
    }
  </script>
</body>
</html>