<?php
// Base URL for the PHP app
define('APP_BASE_URL', rtrim(getenv('APP_BASE_URL') ?: 'http://localhost/New%20Folder', '/'));

// Legacy local Flask service URL (kept for backwards compatibility)
define('AI_SERVICE_URL', getenv('AI_SERVICE_URL') ?: 'http://127.0.0.1:5001/match');

// Optional: external AI API configuration (OpenRouter, no Python needed)
// If AI_API_KEY is set (either here or as an environment variable),
// the app will use the external AI API instead of the local Flask service.
// Replace YOUR_OPENROUTER_API_KEY_HERE with your real OpenRouter key.
define('AI_API_KEY', getenv('AI_API_KEY') ?: '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');
define('AI_API_BASE_URL', getenv('AI_API_BASE_URL') ?: 'https://openrouter.ai/api/v1/chat/completions');
define('AI_API_MODEL', getenv('AI_API_MODEL') ?: 'openai/gpt-4o-mini');
