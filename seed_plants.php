<?php
require_once 'config/database.php';

$plants = [
    [
        'plant_name' => 'Sambong',
        'scientific_name' => 'Blumea balsamifera',
        'description' => 'A flowering plant in the Asteraceae family that grows in tropical Southeast Asia.',
        'illness_treated' => 'Kidney stones, edema, hypertension, and common cold.',
        'preparation_method' => 'Boil clean leaves in water for 15 minutes. Drink as tea 3 times a day.',
        'plant_image' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=500',
        'source_reference' => 'DOH Traditional Medicine List'
    ],
    [
        'plant_name' => 'Lagundi',
        'scientific_name' => 'Vitex negundo',
        'description' => 'A large aromatic shrub with a multi-branched trunk.',
        'illness_treated' => 'Cough, asthma, fever, and muscle pain.',
        'preparation_method' => 'Boil leaves and drink the decoction. For cough, take 1/2 cup three times a day.',
        'plant_image' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=500',
        'source_reference' => 'Philippine Pharmacopeia'
    ],
    [
        'plant_name' => 'Akapulko',
        'scientific_name' => 'Senna alata',
        'description' => 'Also known as ringworm bush, it has yellow flowers and pod-like fruits.',
        'illness_treated' => 'Ringworm, tinea flava, and other fungal skin infections.',
        'preparation_method' => 'Pound fresh leaves and apply the juice directly on the affected skin area.',
        'plant_image' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=500',
        'source_reference' => 'DOH Approved Medicinal Plants'
    ],
    [
        'plant_name' => 'Tsaang Gubat',
        'scientific_name' => 'Ehretia microphylla',
        'description' => 'A shrub with small, shiny green leaves used extensively in traditional medicine.',
        'illness_treated' => 'Abdominal pain, diarrhea, and gastroenteritis.',
        'preparation_method' => 'Boil leaves for 15 minutes. Drink while warm for stomach issues.',
        'plant_image' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=500',
        'source_reference' => 'Folk Medicine Archives'
    ],
    [
        'plant_name' => 'Ampalaya',
        'scientific_name' => 'Momordica charantia',
        'description' => 'Known as bitter melon, a vine with bitter-tasting green fruit.',
        'illness_treated' => 'Type 2 diabetes and high blood sugar levels.',
        'preparation_method' => 'Steam or boil leaves/fruits and eat, or drink as a tea decoction.',
        'plant_image' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=500',
        'source_reference' => 'Global Ethnobotany Database'
    ],
    [
        'plant_name' => 'Bawang',
        'scientific_name' => 'Allium sativum',
        'description' => 'Garlic is a species in the onion genus Allium.',
        'illness_treated' => 'High cholesterol, hypertension, and bacterial infections.',
        'preparation_method' => 'Eat fresh cloves or take as a supplement. Can be roasted or pounded.',
        'plant_image' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=500',
        'source_reference' => 'Natural Health Institute'
    ],
    [
        'plant_name' => 'Ulasimang Bato',
        'scientific_name' => 'Peperomia pellucida',
        'description' => 'A heart-shaped leaf herb that grows in damp, shaded areas.',
        'illness_treated' => 'Gout and arthritis (reduces uric acid levels).',
        'preparation_method' => 'Eat it as a fresh salad or boil leaves for tea.',
        'plant_image' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=500',
        'source_reference' => 'Traditional Philippine Medicine'
    ],
    [
        'plant_name' => 'Yerba Buena',
        'scientific_name' => 'Clinopodium douglasii',
        'description' => 'A creeping fragrance herb, commonly known as peppermint.',
        'illness_treated' => 'Body aches, rheumatism, and nausea.',
        'preparation_method' => 'Boil leaves and drink for pain relief or crush leaves for external application.',
        'plant_image' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=500',
        'source_reference' => 'Herbal Remedies Guide'
    ],
    [
        'plant_name' => 'Niyog-niyogan',
        'scientific_name' => 'Combretum indicum',
        'description' => 'A vine with clusters of fragrant flowers that change color from white to red.',
        'illness_treated' => 'Intestinal worms (parasites).',
        'preparation_method' => 'Eat dried seeds (not more than 10 for adults, 5 for children) 2 hours after dinner.',
        'plant_image' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=500',
        'source_reference' => 'Tropical Botany Journal'
    ],
    [
        'plant_name' => 'Bayabas',
        'scientific_name' => 'Psidium guajava',
        'description' => 'A common tropical tree produced for its fruit and medicinal leaves.',
        'illness_treated' => 'Wounds (antiseptic), toothache, and diarrhea.',
        'preparation_method' => 'Use leaf decoction to wash wounds or gargle for gum infections.',
        'plant_image' => 'https://images.unsplash.com/photo-1596719463953-cc82531cd80e?w=500',
        'source_reference' => 'Antiseptic Herbs Compendium'
    ]
];

try {
    $insertedCount = 0;
    foreach ($plants as $plant) {
        $stmt = $pdo->prepare("INSERT INTO plants (plant_name, scientific_name, description, illness_treated, preparation_method, plant_image, source_reference, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        if ($stmt->execute([
            $plant['plant_name'],
            $plant['scientific_name'],
            $plant['description'],
            $plant['illness_treated'],
            $plant['preparation_method'],
            $plant['plant_image'],
            $plant['source_reference']
        ])) {
            $insertedCount++;
        }
    }
    echo "Successfully inserted $insertedCount medicinal plants.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
