<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once '../config/database.php';

// --- CONFIGURATION ---
// PASTE YOUR PLANT.ID API KEY HERE
$plant_id_api_key = 'WwVjqwB0cocgMZvpBScxdIfTL8qkukPb6FzcwUZPjrXuNesMQh'; 
// ---------------------

$data = json_decode(file_get_contents('php://input'), true);
$base64_image = $data['image'] ?? '';

if (empty($base64_image)) {
    ob_clean();
    echo json_encode(['error' => 'No image provided']);
    exit;
}

// Clean up base64 prefix if present (e.g. "data:image/jpeg;base64,")
if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $type)) {
    $base64_image = substr($base64_image, strpos($base64_image, ',') + 1);
}

// 1. CALL PLANT.ID API
$api_url = "https://api.plant.id/v2/identify";
$payload = json_encode([
    "images" => [$base64_image],
    "latitude" => 14.5995, // Optional: Philippines coordinates
    "longitude" => 120.9842,
    "modifiers" => ["crops_fast", "similar_images"],
    "plant_details" => ["common_names", "scientific_name", "wiki_description", "taxonomy", "treatments"]
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for XAMPP local environments
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Api-Key: $plant_id_api_key"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    $error_msg = curl_error($ch) ?: 'AI Identification service is currently offline.';
    ob_clean();
    echo json_encode(['error' => $error_msg]);
    exit;
}

curl_close($ch);

$api_data = json_decode($response, true);

// If the API explicitly says no plants were found in the image
if (empty($api_data['suggestions'])) {
    ob_clean();
    echo json_encode(['error' => 'No plant recognized in this image. Please ensure the plant is centered and well-lit.']);
    exit;
}

$suggestion = $api_data['suggestions'][0];
$scientific_name = $suggestion['scientific_name'];
$common_name = $suggestion['plant_details']['common_names'][0] ?? $suggestion['plant_name'];
$confidence = round($suggestion['probability'] * 100, 1);

// THE SCAN SHOULD NOT BE RANDOM
// We only pull from the database IF there is a botanical match
try {
    // Strategy: Match by Scientific Name (most accurate) or Common Name
    $stmt = $pdo->prepare("SELECT * FROM plants WHERE scientific_name LIKE ? OR plant_name LIKE ? LIMIT 1");
    $stmt->execute(["%$scientific_name%", "%$common_name%"]);
    $local = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($local) {
        // MATCH FOUND: Combine AI confidence with our verified local wisdom
        ob_clean();
        echo json_encode([
            'name' => $local['plant_name'],
            'scientific_name' => $local['scientific_name'],
            'uses' => $local['illness_treated'],
            'preparation' => $local['preparation_method'],
            'confidence' => $confidence,
            'source' => 'Verified Local Knowledge'
        ]);
    } else {
        // NO LOCAL MATCH: Use the Global AI data only
        // This prevents "Sage vs Guava" - it shows what the AI actually sees
        $wiki_info = $suggestion['plant_details']['wiki_description']['value'] ?? "Information for this species is currently being curated in our local database.";
        
        ob_clean();
        echo json_encode([
            'name' => ucwords($common_name),
            'scientific_name' => $scientific_name,
            'uses' => "AI Insight: " . (strlen($wiki_info) > 200 ? substr($wiki_info, 0, 200) . "..." : $wiki_info),
            'preparation' => "Traditional preparation for " . $common_name . " is still under verification by our local healers.",
            'confidence' => $confidence,
            'source' => 'Global Botanical AI'
        ]);
    }
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['error' => 'Error cross-referencing botanical database.']);
}
ob_end_flush();
?>
