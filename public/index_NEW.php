<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));


/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|


$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
*/

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TheLinkPhone | Be Reachable. Stay Protected.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS is unchanged - keeping it here for single-file example */
        :root {
            --primary-color: #4a00e0;
            --secondary-color: #8e2de2;
            --accent-color: #34d399; /* A vibrant green for CTAs */
            --dark-color: #1a1a2e;
            --light-color: #f4f4f9;
            --text-color: #333;
            --text-light: #ffffff;
            --card-bg: #ffffff;
            --shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Poppins', sans-serif; line-height: 1.6; color: var(--text-color); background-color: var(--light-color); }
        .container { max-width: 1100px; margin: auto; overflow: hidden; padding: 0 2rem; }
        h1, h2, h3 { font-weight: 700; line-height: 1.2; }
        h2 { font-size: 2.5rem; margin-bottom: 1rem; color: var(--dark-color); }
        section { padding: 6rem 0; }
        .section-header { text-align: center; margin-bottom: 3rem; }
        .section-header p { max-width: 600px; margin: 0 auto; color: #666; }
        .btn { display: inline-block; padding: 0.8rem 2rem; background: var(--accent-color); color: var(--text-light); border: none; border-radius: 50px; cursor: pointer; text-decoration: none; font-weight: 600; transition: transform 0.2s ease-in-out, background-color 0.2s ease-in-out; }
        .btn:hover { transform: scale(1.05); background-color: #2cb782; }
        .navbar { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); color: var(--text-color); padding: 1rem 0; position: fixed; width: 100%; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); transition: all 0.3s; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .navbar .logo { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); text-decoration: none; }
        .navbar .logo .icon { display: inline-block; transform: rotate(-45deg); margin-right: 5px; }
        .navbar nav ul { list-style: none; display: flex; }
        .navbar nav ul li { margin-left: 1.5rem; }
        .navbar nav ul li a { color: var(--text-color); text-decoration: none; font-weight: 600; transition: color 0.2s ease-in-out; }
        .navbar nav ul li a:hover { color: var(--primary-color); }
        .navbar .btn { padding: 0.5rem 1.5rem; }
        #hero { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: var(--text-light); height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; position: relative; padding: 0 2rem; }
        #hero .hero-content { max-width: 800px; }
        #hero .hero-icon { font-size: 3rem; margin-bottom: 1rem; }
        #hero h1 { font-size: 3.5rem; margin-bottom: 0.5rem; }
        #hero .tagline { font-size: 1.5rem; font-weight: 400; margin-bottom: 1.5rem; }
        #hero p { font-size: 1.1rem; max-width: 600px; margin: 0 auto 2rem auto; }
        #features .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; text-align: center; }
        #features .feature-item { background: var(--card-bg); padding: 2.5rem 2rem; border-radius: 10px; box-shadow: var(--shadow); transition: transform 0.3s ease; }
        #features .feature-item:hover { transform: translateY(-10px); }
        #features .feature-icon { width: 64px; height: 64px; margin: 0 auto 1.5rem auto; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-light); }
        #features h3 { font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--primary-color); }
        #use-cases { background-color: #fff; }
        .use-case-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .use-case-card { background: var(--light-color); padding: 2rem; border-radius: 10px; display: flex; flex-direction: column; border-left: 5px solid var(--accent-color); }
        .use-case-card .card-icon { font-size: 2rem; margin-bottom: 1rem; }
        .use-case-card h3 { font-size: 1.2rem; margin-bottom: 0.5rem; }
        #business { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: var(--text-light); }
        #business h2 { color: var(--text-light); }
        #business .business-content { display: flex; align-items: center; gap: 4rem; }
        #business .business-text { flex: 1; }
        #business .business-text ul { list-style: none; margin-top: 1.5rem; }
        #business .business-text li { padding-left: 2rem; position: relative; margin-bottom: 1rem; }
        #business .business-text li::before { content: '✓'; position: absolute; left: 0; color: var(--accent-color); font-weight: bold; }
        #business .business-image { flex: 1; text-align: center; }
        #business .business-image svg { max-width: 350px; width: 100%; }
        #bonus .bonus-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        #bonus .bonus-item { text-align: center; }
        #bonus .bonus-item .card-icon { font-size: 2.5rem; margin-bottom: 1rem; color: var(--secondary-color); }
        #final-cta { text-align: center; background: #fff; }
        #final-cta .gift-icon { font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem; }
        #final-cta p { max-width: 600px; margin: 1rem auto 2rem auto; }
        footer { background: var(--dark-color); color: var(--text-light); padding: 4rem 0 2rem 0; }
        .footer-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; }
        .footer-col h4 { margin-bottom: 1rem; color: var(--accent-color); font-weight: 600; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 0.5rem; }
        .footer-col ul li a { color: #ccc; text-decoration: none; transition: color 0.2s; }
        .footer-col ul li a:hover { color: var(--text-light); }
        .footer-col .logo { font-size: 1.5rem; font-weight: 700; color: var(--text-light); margin-bottom: 1rem; }
        .footer-bottom { text-align: center; margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #333a55; font-size: 0.9rem; color: #aaa; }
        @media(max-width: 768px) {
            h1 { font-size: 2.8rem; }
            h2 { font-size: 2rem; }
            section { padding: 4rem 0; }
            .container { padding: 0 1rem; }
            .navbar .container { flex-direction: column; }
            .navbar nav { margin-top: 1rem; }
            .navbar nav ul li { margin: 0 0.7rem; }
            #hero { height: auto; padding: 10rem 1rem 4rem 1rem; }
            #features .features-grid { grid-template-columns: 1fr; }
            #business .business-content { flex-direction: column; gap: 2rem; }
        }
    </style>
</head>
<body>

    <header class="navbar">
        <div class="container">
            <a href="{{ url('/') }}" class="logo"> <span class="icon">🌀</span>TheLinkPhone
            </a>
            <nav>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#use-cases">Use Cases</a></li>
                    <li><a href="{{ url('/pricing') }}">Pricing</a></li>
                    <li><a href="{{ url('/contact') }}">Contact</a></li>
                    <li><a href="{{ url('/login') }}"><b>Login</b></a></li> </ul>
            </nav>
            <a href="{{ route('sign.up') }}" class="btn">Start Now</a>
        </div>
    </header>

    <main>

        <section id="hero">
            <div class="hero-content">
                <div class="hero-icon">🔗</div>
                <h1>TheLinkPhone</h1>
                <p class="tagline">Be Reachable. Stay Protected.</p>
                <p>
                    With TheLinkPhone, you no longer need to give out your mobile number to be reachable.
                    Instead, you share unique, customizable links that connect callers to you—only when, and if, you want.
                </p>
                <a href="{{ route('sign.up') }}" class="btn">Get Your Link</a>
            </div>
        </section>

        <section id="features">
            <div class="container">
                <div class="section-header">
                    <h2>🔐 Invisible Numbers. Visible Control.</h2>
                    <p>Whether it’s a dating app, business pitch, or a customer inquiry, you stay connected—without giving away your number.</p>
                </div>
                <div class="features-grid">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.72"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.72-1.72"></path></svg>
                        </div>
                        <h3>Call Your Link, Not You</h3>
                        <p>People call your unique link, never your personal number. Your privacy is locked down from the start.</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        </div>
                        <h3>Total Control</h3>
                        <p>Organize callers into groups, block or unblock anyone with a click, and filter out spam calls effortlessly.</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </div>
                        <h3>Timed Access</h3>
                        <p>Set up calendar-based time slots for calls. Any attempts outside your approved hours are automatically blocked.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="use-cases">
            <div class="container">
                <div class="section-header">
                    <h2>💼 Real-Life Magic: Use Cases</h2>
                </div>
                <div class="use-case-grid">
                    <div class="use-case-card">
                        <div class="card-icon">🏡</div>
                        <h3>House Hunter</h3>
                        <p>Separate links for brokers, owners, or listings. Track calls without chaos and keep your number private.</p>
                    </div>
                    <div class="use-case-card">
                        <div class="card-icon">🧑‍🏫</div>
                        <h3>Teachers & Professors</h3>
                        <p>Talk to students during scheduled hours only. Your privacy is protected and distractions are eliminated.</p>
                    </div>
                    <div class="use-case-card">
                        <div class="card-icon">❤️</div>
                        <h3>Matrimonial Profiles</h3>
                        <p>Share a link, not a number. Preferential callers get through, while others are silently blocked.</p>
                    </div>
                    <div class="use-case-card">
                        <div class="card-icon">✈️</div>
                        <h3>Frequent Travelers</h3>
                        <p>Airlines call a link you control for updates, upgrades, and delays, all without cluttering your main line.</p>
                    </div>
                    <div class="use-case-card">
                        <div class="card-icon">🎯</div>
                        <h3>Sales & Marketing Pros</h3>
                        <p>Let prospects book calls via a link in emails or forms. Appointments auto-open access, then shut down.</p>
                    </div>
                    <div class="use-case-card">
                        <div class="card-icon">👶</div>
                        <h3>Social Circles</h3>
                        <p>Share advice, recipes, or reviews in mom groups or clubs. Stay social without risking your phone number.</p>
                    </div>
                </div>
            </div>
        </section>
        <section id="business">
            <div class="container">
                <div class="business-content">
                    <div class="business-text">
                        <h2>🌐 For Businesses & Teams</h2>
                        <p>Elevate your customer experience and internal communications with smart, flexible call routing.</p>
                        <ul>
                            <li>Create shift-based links for support teams, banks, or hotel crews.</li>
                            <li>Clients just click—no memorizing numbers or asking who’s on duty.</li>
                            <li>Improve customer experience and eliminate frustrating routing delays.</li>
                        </ul>
                    </div>
                    <div class="business-image">
                       <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" fill="none">
                           <path d="M78.53 175c-27.34 0-49.5-22.16-49.5-49.5s22.16-49.5 49.5-49.5" stroke="#ffffff" stroke-width="10" stroke-linecap="round"/>
                           <path d="M121.47 25c27.34 0 49.5 22.16 49.5 49.5s-22.16 49.5-49.5 49.5" stroke="#ffffff" stroke-width="10" stroke-linecap="round"/>
                           <circle cx="78.53" cy="76" r="21.5" fill="#34d399"/>
                           <circle cx="121.47" cy="124" r="21.5" fill="#34d399"/>
                       </svg>
                    </div>
                </div>
            </div>
        </section>
        <section id="bonus">
            <div class="container">
                <div class="section-header">
                    <h2>🎬 Bonus Magic</h2>
                    <p>Discover clever ways TheLinkPhone simplifies everyday interactions.</p>
                </div>
                <div class="bonus-grid">
                    <div class="bonus-item">
                        <div class="card-icon">🍿</div>
                        <h3>Snacks at the Movies</h3>
                        <p>Link directly to the snack counter. No app, no hassle.</p>
                    </div>
                    <div class="bonus-item">
                        <div class="card-icon">✍️</div>
                        <h3>Public Reviews</h3>
                        <p>Let businesses respond to your review on your terms.</p>
                    </div>
                     <div class="bonus-item">
                        <div class="card-icon">👮</div>
                        <h3>Instant Routing</h3>
                        <p>Route calls to the closest police patrol or concierge desk.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="final-cta">
            <div class="container">
                <div class="gift-icon">🎁</div>
                <h2>Live TheLinkPhone Life</h2>
                <p>
                    Be social. Be professional. Be open. Just don’t give your number away.
                    TheLinkPhone lets you stay in touch while staying in control. Privacy meets practicality. That’s the link life.
                </p>
                <a href="{{ route('sign.up') }}" class="btn">👉 Get Started Now</a>
            </div>
        </section>

    </main>

    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-col">
                    <div class="logo">TheLinkPhone</div>
                    <p>Be Reachable. Stay Protected.</p>
                </div>
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#use-cases">Use Cases</a></li>
                        <li><a href="{{ url('/pricing') }}">Pricing</a></li>
                    </ul>
                </div>
                 <div class="footer-col">
                    <h4>Company</h4>
                    <ul>
                        <li><a href="{{ url('/about') }}">About Us</a></li>
                        <li><a href="{{ url('/contact') }}">Contact Us</a></li>
                        <li><a href="{{ url('/terms') }}">Terms & Conditions</a></li>
                    </ul>
                </div>
                 <div class="footer-col">
                    <h4>Connect</h4>
                    <ul>
                        <li><a href="#">Twitter</a></li>
                        <li><a href="#">LinkedIn</a></li>
                        <li><a href="#">Facebook</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; 2025 TheLinkPhone. All Rights Reserved.
            </div>
        </div>
    </footer>

</body>
</html>