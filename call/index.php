<?php
// LinkPhone Deep Link Redirect Handler
// File: /call/index.php

// Get the user ID from the URL path
$requestUri = $_SERVER['REQUEST_URI'];
$userId = null;

// Extract user ID from URL like: /call/codpr1044p
if (preg_match('/\/call\/([^\/\?]+)/', $requestUri, $matches)) {
    $userId = trim($matches[1]);
}

if ($userId && !empty($userId)) {
    // Create the app deep link
    $appDeepLink = "thelinkphone.com";
    // $appDeepLink = "linkphone://call?user=" . urlencode($userId);
    
    // Log the redirect attempt (optional)
    error_log("LinkPhone redirect: " . $userId . " -> " . $appDeepLink);
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Opening LinkPhone App...</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                text-align: center;
                padding: 20px;
                color: white;
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }

             #canvas {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 0;
            }

            /* Animated gradient mesh background */
            .gradient-mesh {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: 
                    radial-gradient(ellipse at 10% 20%, rgba(120, 81, 169, 0.4) 0%, transparent 50%),
                    radial-gradient(ellipse at 80% 80%, rgba(255, 107, 107, 0.3) 0%, transparent 50%),
                    radial-gradient(ellipse at 50% 50%, rgba(78, 205, 196, 0.3) 0%, transparent 50%),
                    radial-gradient(ellipse at 90% 10%, rgba(199, 121, 208, 0.3) 0%, transparent 50%);
                filter: blur(80px);
                animation: meshFloat 25s ease-in-out infinite;
                z-index: 1;
            }

            @keyframes meshFloat {
                0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); }
                25% { transform: translate(5%, -5%) scale(1.1) rotate(90deg); }
                50% { transform: translate(-5%, 5%) scale(0.9) rotate(180deg); }
                75% { transform: translate(5%, 5%) scale(1.05) rotate(270deg); }
            }

            /* Noise texture overlay */
            .noise-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
                opacity: 0.03;
                z-index: 2;
                pointer-events: none;
            }

            /* 3D rotating grid */
            .grid-3d {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                perspective: 1000px;
                z-index: 1;
            }

            .grid-layer {
                position: absolute;
                width: 200%;
                height: 200%;
                top: -50%;
                left: -50%;
                background-image: 
                    linear-gradient(rgba(102, 126, 234, 0.1) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(102, 126, 234, 0.1) 1px, transparent 1px);
                background-size: 40px 40px;
                animation: grid3DRotate 30s linear infinite;
                transform-style: preserve-3d;
            }

            @keyframes grid3DRotate {
                0% { transform: rotateX(60deg) rotateZ(0deg); }
                100% { transform: rotateX(60deg) rotateZ(360deg); }
            }

            /* Aurora effect */
            .aurora {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1;
            }

            .aurora-layer {
                position: absolute;
                width: 200%;
                height: 100%;
                top: -50%;
                background: linear-gradient(to bottom, 
                    transparent 0%,
                    rgba(102, 126, 234, 0.2) 30%,
                    rgba(138, 43, 226, 0.2) 50%,
                    rgba(255, 107, 107, 0.2) 70%,
                    transparent 100%);
                animation: auroraMove 20s ease-in-out infinite;
                filter: blur(60px);
                opacity: 0.5;
            }

            .aurora-layer:nth-child(2) {
                animation-duration: 25s;
                animation-delay: -5s;
            }

            .aurora-layer:nth-child(3) {
                animation-duration: 30s;
                animation-delay: -10s;
            }

            @keyframes auroraMove {
                0%, 100% { transform: translateX(-25%) translateY(0) skewX(-10deg); }
                50% { transform: translateX(0%) translateY(-20px) skewX(10deg); }
            }

            /* Particle system */
            .particles-advanced {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 2;
            }

            .particle-dot {
                position: absolute;
                width: 3px;
                height: 3px;
                background: radial-gradient(circle, rgba(255, 255, 255, 0.8), transparent);
                border-radius: 50%;
                pointer-events: none;
            }

            /* Floating orbs with trails */
            .orb-system {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 2;
            }

            .floating-orb {
                position: absolute;
                width: 150px;
                height: 150px;
                border-radius: 50%;
                background: radial-gradient(circle at 30% 30%, 
                    rgba(102, 126, 234, 0.6), 
                    rgba(138, 43, 226, 0.3) 50%,
                    transparent 70%);
                filter: blur(30px);
                animation: orbFloat 15s ease-in-out infinite;
            }

            .floating-orb:nth-child(1) { animation-duration: 20s; }
            .floating-orb:nth-child(2) { animation-duration: 25s; animation-delay: -5s; }
            .floating-orb:nth-child(3) { animation-duration: 30s; animation-delay: -10s; }

            @keyframes orbFloat {
                0%, 100% { 
                    transform: translate(0, 0) scale(1);
                    opacity: 0.6;
                }
                25% { 
                    transform: translate(40vw, -20vh) scale(1.2);
                    opacity: 0.8;
                }
                50% { 
                    transform: translate(80vw, 30vh) scale(0.8);
                    opacity: 0.5;
                }
                75% { 
                    transform: translate(20vw, 60vh) scale(1.1);
                    opacity: 0.7;
                }
            }

            /* DNA Helix animation */
            .dna-helix {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1;
                opacity: 0.15;
            }

            .dna-strand {
                position: absolute;
                width: 4px;
                height: 100%;
                background: linear-gradient(to bottom, 
                    transparent,
                    rgba(102, 126, 234, 0.8),
                    transparent);
                animation: dnaRotate 8s linear infinite;
            }

            @keyframes dnaRotate {
                0% { transform: translateX(0) rotateY(0deg); }
                100% { transform: translateX(0) rotateY(360deg); }
            }

            /* Vortex effect */
            .vortex {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 800px;
                height: 800px;
                z-index: 1;
            }

            .vortex-ring {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                border: 1px solid rgba(102, 126, 234, 0.2);
                border-radius: 50%;
                animation: vortexExpand 4s ease-out infinite;
            }

            @keyframes vortexExpand {
                0% {
                    width: 0;
                    height: 0;
                    opacity: 1;
                }
                100% {
                    width: 800px;
                    height: 800px;
                    opacity: 0;
                }
            }

            .container {
                max-width: 400px;
                background: rgba(255,255,255,0.1);
                padding: 40px 30px;
                border-radius: 20px;
                backdrop-filter: blur(10px);
                box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            }
            .logo {
                font-size: 64px;
                margin-bottom: 20px;
                animation: pulse 2s infinite;
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            .spinner {
                border: 3px solid rgba(255,255,255,0.3);
                border-top: 3px solid white;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 20px auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .btn {
                background: #4CAF50;
                color: white;
                padding: 15px 30px;
                border: none;
                border-radius: 25px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin: 10px;
                transition: all 0.3s ease;
            }
            .btn:hover {
                background: #45a049;
                transform: translateY(-2px);
            }
            #fallback {
                display: none;
                margin-top: 30px;
                animation: fadeIn 0.5s ease-in;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .user-id {
                font-family: monospace;
                background: rgba(255,255,255,0.2);
                padding: 5px 10px;
                border-radius: 5px;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        
        <!-- Canvas for particle effects -->
        <canvas id="canvas"></canvas>

        <!-- Advanced Background Layers -->
        <div class="gradient-mesh"></div>
        <div class="noise-overlay"></div>
        
        <div class="grid-3d">
            <div class="grid-layer"></div>
        </div>

        <div class="aurora">
            <div class="aurora-layer"></div>
            <div class="aurora-layer"></div>
            <div class="aurora-layer"></div>
        </div>

        <div class="particles-advanced" id="particles-advanced"></div>

        <div class="orb-system">
            <div class="floating-orb"></div>
            <div class="floating-orb"></div>
            <div class="floating-orb"></div>
        </div>

        <div class="dna-helix" id="dna-helix"></div>

        <div class="vortex">
            <div class="vortex-ring" style="animation-delay: 0s;"></div>
            <div class="vortex-ring" style="animation-delay: 0.5s;"></div>
            <div class="vortex-ring" style="animation-delay: 1s;"></div>
            <div class="vortex-ring" style="animation-delay: 1.5s;"></div>
            <div class="vortex-ring" style="animation-delay: 2s;"></div>
        </div>

        <!-- Cursor glow -->
        <div class="cursor-glow" id="cursor-glow"></div>

        <div class="container">
            <div class="logo">📱</div>
            <h2>Opening LinkPhone App...</h2>
            <div class="spinner"></div>
            <p>Redirecting you to make a call...</p>
            
            <div id="fallback">
                <h3>y?</h3>
                <p>Make sure LinkPhone app is installed on your device.</p>
                <a href="<?php echo htmlspecialchars($appDeepLink); ?>" class="btn">Open LinkPhone App</a>
                <br><br>
                <p><small>User ID: <span class="user-id"><?php echo htmlspecialchars($userId); ?></span></small></p>
                <p><small>If you don't have the app, please install LinkPhone first.</small></p>
            </div>
        </div>

        <script>
            // Try to open the app immediately
            console.log('Attempting to open LinkPhone app...');
            window.location.href = "<?php echo $appDeepLink; ?>";

            const canvas = document.getElementById('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            class AdvancedParticle {
                constructor() {
                    this.x = Math.random() * canvas.width;
                    this.y = Math.random() * canvas.height;
                    this.size = Math.random() * 2 + 1;
                    this.speedX = Math.random() * 2 - 1;
                    this.speedY = Math.random() * 2 - 1;
                    this.opacity = Math.random() * 0.5 + 0.2;
                    this.color = this.randomColor();
                }

                randomColor() {
                    const colors = [
                        'rgba(102, 126, 234,',
                        'rgba(138, 43, 226,',
                        'rgba(255, 107, 107,',
                        'rgba(78, 205, 196,'
                    ];
                    return colors[Math.floor(Math.random() * colors.length)];
                }

                update() {
                    this.x += this.speedX;
                    this.y += this.speedY;

                    if (this.x > canvas.width) this.x = 0;
                    if (this.x < 0) this.x = canvas.width;
                    if (this.y > canvas.height) this.y = 0;
                    if (this.y < 0) this.y = canvas.height;

                    this.opacity = Math.sin(Date.now() * 0.001 + this.x) * 0.3 + 0.4;
                }

                draw() {
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fillStyle = this.color + this.opacity + ')';
                    ctx.fill();

                    // Draw glow
                    ctx.shadowBlur = 10;
                    ctx.shadowColor = this.color + this.opacity + ')';
                }
            }

            const particles = [];
            for (let i = 0; i < 150; i++) {
                particles.push(new AdvancedParticle());
            }

            // Connect nearby particles
            function connectParticles() {
                for (let i = 0; i < particles.length; i++) {
                    for (let j = i + 1; j < particles.length; j++) {
                        const dx = particles[i].x - particles[j].x;
                        const dy = particles[i].y - particles[j].y;
                        const distance = Math.sqrt(dx * dx + dy * dy);

                        if (distance < 120) {
                            ctx.beginPath();
                            ctx.moveTo(particles[i].x, particles[i].y);
                            ctx.lineTo(particles[j].x, particles[j].y);
                            ctx.strokeStyle = `rgba(102, 126, 234, ${0.2 * (1 - distance / 120)})`;
                            ctx.lineWidth = 0.5;
                            ctx.stroke();
                        }
                    }
                }
            }

            function animateCanvas() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                particles.forEach(particle => {
                    particle.update();
                    particle.draw();
                });

                connectParticles();
                requestAnimationFrame(animateCanvas);
            }

            animateCanvas();

            // Create DNA helix strands
            const dnaHelix = document.getElementById('dna-helix');
            for (let i = 0; i < 20; i++) {
                const strand = document.createElement('div');
                strand.className = 'dna-strand';
                strand.style.left = `${i * 5}%`;
                strand.style.animationDelay = `${i * 0.1}s`;
                dnaHelix.appendChild(strand);
            }

            // Cursor glow effect
            const cursorGlow = document.getElementById('cursor-glow');
            document.addEventListener('mousemove', (e) => {
                cursorGlow.style.left = e.clientX + 'px';
                cursorGlow.style.top = e.clientY + 'px';
            });

            // Canvas resize
            window.addEventListener('resize', () => {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            });
            
            // Show fallback options after 2.5 seconds
            setTimeout(function() {
                document.getElementById('fallback').style.display = 'block';
                console.log('Showing fallback options');
            }, 2500);
            
            // Alternative method using iframe (works better on some browsers)
            setTimeout(function() {
                try {
                    var iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = "<?php echo $appDeepLink; ?>";
                    document.body.appendChild(iframe);
                    console.log('Tried iframe method');
                } catch(e) {
                    console.log('Iframe method failed:', e);
                }
            }, 1000);
            
            // Handle page visibility change (user switched back to browser)
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    // User came back to browser, show fallback
                    setTimeout(function() {
                        document.getElementById('fallback').style.display = 'block';
                    }, 1000);
                }
            });
        </script>
    </body>
    </html>
    <?php
} else {
    // Invalid URL format
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invalid LinkPhone Link</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                text-align: center;
                padding: 50px 20px;
                background: #f5f5f5;
                color: #333;
                margin: 0;
            }
            .container {
                max-width: 400px;
                margin: 0 auto;
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .error-icon {
                font-size: 48px;
                color: #ff6b6b;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-icon">⚠️</div>
            <h2>Invalid LinkPhone Link</h2>
            <p>This LinkPhone link appears to be invalid or malformed.</p>
            <p>Please ask the sender to generate a new link from the LinkPhone app.</p>
        </div>
    </body>
    </html>
    <?php
}
?>
