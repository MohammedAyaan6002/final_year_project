<?php
require_once 'includes/config.php';
require_once 'includes/helpers.php';

echo "<h2>AI Setup Test</h2>";

// Check if AI API key is configured
echo "<h3>1. AI API Key Configuration:</h3>";
if (!defined('AI_API_KEY')) {
    echo "<p style='color: red;'>✗ AI_API_KEY is not defined in config.php</p>";
} elseif (AI_API_KEY === '' || AI_API_KEY === 'YOUR_OPENROUTER_API_KEY_HERE') {
    echo "<p style='color: red;'>✗ AI_API_KEY is not set. Please set it in includes/config.php</p>";
    echo "<p><strong>Current value:</strong> '" . AI_API_KEY . "'</p>";
} else {
    echo "<p style='color: green;'>✓ AI_API_KEY is configured</p>";
    echo "<p><strong>Key starts with:</strong> " . substr(AI_API_KEY, 0, 10) . "...</p>";
}

// Check AI API model
echo "<h3>2. AI API Model:</h3>";
if (!defined('AI_API_MODEL')) {
    echo "<p style='color: orange;'>⚠ AI_API_MODEL not defined, using default</p>";
    echo "<p><strong>Default model:</strong> openai/gpt-4o-mini</p>";
} else {
    echo "<p style='color: green;'>✓ AI_API_MODEL is configured</p>";
    echo "<p><strong>Model:</strong> " . AI_API_MODEL . "</p>";
}

// Check if cURL is available
echo "<h3>3. cURL Support:</h3>";
if (function_exists('curl_version')) {
    $curl_version = curl_version();
    echo "<p style='color: green;'>✓ cURL is available</p>";
    echo "<p><strong>Version:</strong> " . $curl_version['version'] . "</p>";
} else {
    echo "<p style='color: red;'>✗ cURL is not available. AI features will not work.</p>";
}

// Test API connection
echo "<h3>4. API Connection Test:</h3>";
if (defined('AI_API_KEY') && AI_API_KEY !== '' && AI_API_KEY !== 'YOUR_OPENROUTER_API_KEY_HERE') {
    echo "<p>Testing connection to OpenRouter API...</p>";
    
    $testData = [
        'model' => defined('AI_API_MODEL') ? AI_API_MODEL : 'openai/gpt-4o-mini',
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Say "AI is working!" in your response.'
            ]
        ],
        'max_tokens' => 10
    ];
    
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AI_API_KEY,
        'HTTP-Referer: ' . (defined('APP_BASE_URL') ? APP_BASE_URL : 'http://localhost'),
        'X-Title: Loyola Lost & Found AI Test',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "<p style='color: red;'>✗ cURL Error: " . htmlspecialchars($error) . "</p>";
    } elseif ($httpCode === 200) {
        echo "<p style='color: green;'>✓ API connection successful!</p>";
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            echo "<p><strong>AI Response:</strong> " . htmlspecialchars($data['choices'][0]['message']['content']) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ API Error (HTTP $httpCode)</p>";
        if ($response) {
            $errorData = json_decode($response, true);
            if (isset($errorData['error'])) {
                echo "<p><strong>Error Message:</strong> " . htmlspecialchars($errorData['error']['message']) . "</p>";
            }
        }
    }
} else {
    echo "<p style='color: orange;'>⚠ Skipping API test - API key not configured</p>";
}

// Instructions
echo "<hr>";
echo "<h3>Setup Instructions:</h3>";
echo "<ol>";
echo "<li><strong>Get OpenRouter API Key:</strong> Go to <a href='https://openrouter.ai/' target='_blank'>openrouter.ai</a> and sign up for a free account</li>";
echo "<li><strong>Copy API Key:</strong> Get your API key from the OpenRouter dashboard</li>";
echo "<li><strong>Edit config.php:</strong> Open <code>includes/config.php</code></li>";
echo "<li><strong>Set API Key:</strong> Replace <code>YOUR_OPENROUTER_API_KEY_HERE</code> with your actual API key</li>";
echo "<li><strong>Optional - Set Model:</strong> You can set <code>AI_API_MODEL</code> to use a different model</li>";
echo "</ol>";

echo "<h4>Example config.php settings:</h4>";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
echo "define('AI_API_KEY', 'sk-or-v1-your-actual-api-key-here');";
echo "\ndefine('AI_API_MODEL', 'openai/gpt-4o-mini'); // Free model";
echo "</pre>";

echo "<h4>Available Free Models:</h4>";
echo "<ul>";
echo "<li><code>openai/gpt-4o-mini</code> - Fast and cheap</li>";
echo "<li><code>meta-llama/llama-3.2-3b-instruct:free</code> - Small but capable</li>";
echo "<li><code>meta-llama/llama-3.1-8b-instruct:free</code> - Good balance</li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='index.php'>Back to Home</a></p>";
echo "<p><a href='pages/search.php'>Test AI Search</a></p>";
?>
