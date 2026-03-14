<?php
require_once 'config/database.php';

$updates = [
    'Sambong' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=800',
    'Lagundi' => 'https://images.unsplash.com/photo-1541014741259-df549fa9ba6f?w=800',
    'Akapulko' => 'https://images.unsplash.com/photo-1509423424749-171e69c4d03d?w=800',
    'Tsaang Gubat' => 'https://images.unsplash.com/photo-1589133415873-138374828e83?w=800',
    'Ampalaya' => 'https://images.unsplash.com/photo-1587334274328-64186a80aeee?w=800',
    'Bawang' => 'https://images.unsplash.com/photo-1563204983-6677f516718d?w=800',
    'Ulasimang Bato' => 'https://images.unsplash.com/photo-1550951298-5c7b95a6ecfb?w=800',
    'Yerba Buena' => 'https://images.unsplash.com/photo-1533602166986-107f9c211155?w=800',
    'Niyog-niyogan' => 'https://images.unsplash.com/photo-1584362917165-526a968579e8?w=800',
    'Bayabas' => 'https://images.unsplash.com/photo-1614735241165-6756e1df61ab?w=800'
];

try {
    $count = 0;
    foreach ($updates as $name => $img) {
        $stmt = $pdo->prepare("UPDATE plants SET plant_image = ? WHERE plant_name = ?");
        $stmt->execute([$img, $name]);
        $count += $stmt->rowCount();
    }
    echo "Successfully updated $count plant images.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
