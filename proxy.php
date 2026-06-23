<?php
/* ============================================================
   USA NUMBER LOOKUP - BACKEND PROXY
   ============================================================ */

// Enable CORS for all domains
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get parameters
$action = $_GET['action'] ?? '';
$phone = $_GET['x'] ?? '';

// Validate phone number
if (empty($phone) || strlen($phone) !== 10 || !preg_match('/^\d{10}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number. Must be 10 digits.']);
    exit;
}

// API endpoints
$apiEndpoints = [
    'person' => 'https://api.infolookup.site/v1/',
    'tcpa'   => 'https://api.infolookup.site/tcpa/v1'
];

// Determine which API to call
if ($action === 'person') {
    $url = $apiEndpoints['person'] . '?x=' . urlencode($phone);
} elseif ($action === 'tcpa') {
    $url = $apiEndpoints['tcpa'] . '?x=' . urlencode($phone);
} elseif ($action === 'both') {
    // Fetch both in parallel
    $result = fetchBoth($phone);
    echo json_encode($result);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action. Use: person, tcpa, or both']);
    exit;
}

// Fetch single API
$result = fetchApi($url);
echo json_encode($result);

// ============================================================
// FUNCTIONS
// ============================================================

function fetchApi($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Referer: https://www.google.com/'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['status' => 'error', 'message' => 'CURL Error: ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['status' => 'error', 'message' => 'API returned HTTP ' . $httpCode];
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        return ['status' => 'error', 'message' => 'Invalid JSON response'];
    }
    
    return $data;
}

function fetchBoth($phone) {
    $personUrl = 'https://api.infolookup.site/v1/?x=' . urlencode($phone);
    $tcpaUrl = 'https://api.infolookup.site/tcpa/v1?x=' . urlencode($phone);
    
    // Multi-curl for parallel requests
    $mh = curl_multi_init();
    
    $ch1 = curl_init();
    curl_setopt($ch1, CURLOPT_URL, $personUrl);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch1, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch1, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Referer: https://www.google.com/'
    ]);
    curl_multi_add_handle($mh, $ch1);
    
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $tcpaUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Referer: https://www.google.com/'
    ]);
    curl_multi_add_handle($mh, $ch2);
    
    // Execute all queries simultaneously
    do {
        curl_multi_exec($mh, $running);
    } while ($running > 0);
    
    $personResponse = curl_multi_getcontent($ch1);
    $tcpaResponse = curl_multi_getcontent($ch2);
    
    curl_multi_remove_handle($mh, $ch1);
    curl_multi_remove_handle($mh, $ch2);
    curl_multi_close($mh);
    
    return [
        'person' => json_decode($personResponse, true),
        'tcpa' => json_decode($tcpaResponse, true)
    ];
}
?>
