<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opening CallALink App...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
            border: 1px solid rgba(255,255,255,0.2);
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
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        .btn:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }
        .btn:active {
            transform: translateY(0);
        }
        #fallback {
            display: none;
            margin-top: 30px;
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .user-id {
            font-family: 'Courier New', monospace;
            background: rgba(255,255,255,0.2);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 14px;
            word-break: break-all;
            margin: 10px 0;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .instructions {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4CAF50;
            margin-right: 8px;
            animation: blink 1.5s infinite;
        }
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        .error-state {
            color: #ffcccb;
        }
        .success-state {
            color: #90EE90;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">📱</div>
        <h2><span class="status-indicator"></span>Opening CallALink App...</h2>
        <div class="spinner"></div>
        <p>Please wait while we redirect you to make a call...</p>
        
        <div id="fallback">
            <h3>App didn't open automatically?</h3>
            <p>Make sure CallALink app is installed on your device.</p>
            
            <a href="{{ $appDeepLink }}" class="btn" id="retry-btn">
                📱 Open CallALink App
            </a>
            
            <div class="instructions">
                <h4>📋 Instructions:</h4>
                <ol style="text-align: left; display: inline-block;">
                    <li>Make sure CallALink app is installed</li>
                    <li>Click the "Open CallALink App" button above</li>
                    <li>Allow the app to open when prompted</li>
                    <li>The call will be placed automatically</li>
                </ol>
            </div>
            
            <div class="user-id">
                <strong>User ID:</strong> {{ $userId }}
            </div>
            
            <p><small>If you don't have the CallALink app, please install it from your app store first.</small></p>
        </div>
    </div>

    <script>
        console.log('CallALink redirect page loaded');
        console.log('User ID: {{ $userId }}');
        console.log('App deep link: {{ $appDeepLink }}');
        
        let redirectAttempted = false;
        let fallbackShown = false;
        
        // Function to attempt app opening
        function attemptAppOpen() {
            if (redirectAttempted) return;
            redirectAttempted = true;
            
            console.log('Attempting to open CallALink app...');
            
            // Method 1: Direct window location change
            try {
                window.location.href = "{{ $appDeepLink }}";
                console.log('Method 1: Direct redirect attempted');
            } catch (e) {
                console.error('Method 1 failed:', e);
            }
            
            // Method 2: Create invisible iframe (works better on some browsers)
            setTimeout(function() {
                try {
                    var iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.style.width = '1px';
                    iframe.style.height = '1px';
                    iframe.src = "{{ $appDeepLink }}";
                    document.body.appendChild(iframe);
                    console.log('Method 2: Iframe method attempted');
                    
                    // Remove iframe after a short delay
                    setTimeout(function() {
                        if (iframe.parentNode) {
                            iframe.parentNode.removeChild(iframe);
                        }
                    }, 2000);
                } catch (e) {
                    console.error('Method 2 failed:', e);
                }
            }, 500);
            
            // Method 3: Create temporary link and click it
            setTimeout(function() {
                try {
                    var link = document.createElement('a');
                    link.href = "{{ $appDeepLink }}";
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    console.log('Method 3: Link click method attempted');
                } catch (e) {
                    console.error('Method 3 failed:', e);
                }
            }, 1000);
        }
        
        // Function to show fallback options
        function showFallback() {
            if (fallbackShown) return;
            fallbackShown = true;
            
            console.log('Showing fallback options');
            document.getElementById('fallback').style.display = 'block';
            
            // Update status indicator
            const indicator = document.querySelector('.status-indicator');
            if (indicator) {
                indicator.style.background = '#ff9800';
                indicator.style.animation = 'none';
            }
            
            // Update main heading
            const heading = document.querySelector('h2');
            if (heading) {
                heading.innerHTML = '<span class="status-indicator" style="background: #ff9800;"></span>Need help opening the app?';
            }
            
            // Hide spinner
            const spinner = document.querySelector('.spinner');
            if (spinner) {
                spinner.style.display = 'none';
            }
        }
        
        // Start the redirect process immediately
        attemptAppOpen();
        
        // Show fallback after 3 seconds
        setTimeout(showFallback, 3000);
        
        // Handle page visibility change (user switched back to browser)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && redirectAttempted) {
                // User came back to browser, show fallback immediately
                setTimeout(showFallback, 500);
            }
        });
        
        // Handle retry button click
        document.addEventListener('DOMContentLoaded', function() {
            const retryBtn = document.getElementById('retry-btn');
            if (retryBtn) {
                retryBtn.addEventListener('click', function(e) {
                    console.log('Retry button clicked');
                    
                    // Add visual feedback
                    retryBtn.style.background = '#45a049';
                    retryBtn.innerHTML = '🔄 Opening...';
                    
                    setTimeout(function() {
                        retryBtn.style.background = '#4CAF50';
                        retryBtn.innerHTML = '📱 Open CallALink App';
                    }, 2000);
                });
            }
        });
        
        // Handle back button (Android)
        window.addEventListener('popstate', function(event) {
            console.log('Back button pressed');
            showFallback();
        });
        
        // Log when page is about to unload
        window.addEventListener('beforeunload', function() {
            console.log('Page unloading - app may have opened successfully');
        });
        
        // Additional debugging
        console.log('Redirect page setup complete');
        console.log('User agent:', navigator.userAgent);
        console.log('Platform:', navigator.platform);
    </script>
</body>
</html>
