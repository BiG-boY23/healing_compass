<?php
require_once 'config/database.php';

$healers_images = [
    'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=300',
    'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=300',
    'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=300',
    'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=300',
    'https://images.unsplash.com/photo-1544717297-fa35b33a2e50?w=300'
];

try {
    $stmt = $pdo->query("SELECT id FROM healers");
    $healers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = 0;
    foreach ($healers as $index => $h) {
        $img = $healers_images[$index % count($healers_images)];
        $pdo->prepare("UPDATE healers SET profile_picture = ? WHERE id = ?")->execute([$img, $h['id']]);
        $count++;
    }
    echo "Successfully updated $count healers with profile pictures.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
