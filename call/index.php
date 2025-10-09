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
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
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
        <div class="container">
            <div class="logo">📱</div>
            <h2>Opening LinkPhone App...</h2>
            <div class="spinner"></div>
            <p>Redirecting you to make a call...</p>
            
            <div id="fallback">
                <h3>App didn't open automatically?</h3>
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
