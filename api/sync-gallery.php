<?php
/**
 * One-click live gallery database synchronization.
 * Updates all 29 authentic designs into gallery_items.
 *
 * Usage:
 *   https://reignfashionempire.com/api/sync-gallery.php?key=reign2026
 */
declare(strict_types=1);

require_once __DIR__ . '/../backend/gallery.php';

header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
$config = config();
$validKey = ($key === 'reign2026' || $key === ($config['app_secret'] ?? ''));

if (!$validKey) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid or missing synchronization key. Use ?key=reign2026']);
    exit;
}

try {
    $pdo = db();

    $items = [
        [1, 'Crystal Corset Mermaid', 'Reign Fashion bridal: crystal-studded corset mermaid gown with a dramatic sparkling train', 'bridal', 'tall', 'assets/images/bridal/gown-crystal-corset.webp', 1200, 1500, '50% 30%', 0, 1, 10],
        [2, 'Champagne Rose Beaded Corset', 'Reign Fashion Aso-Ebi: Draped champagne & rose gold beaded corset gown with pleated gold gele', 'aso-ebi', 'tall', 'assets/images/aso-ebi/aso-ebi-champagne-rose.jpg', 1080, 1920, '50% 15%', 0, 1, 20],
        [3, 'Burgundy Peplum Sash Jumpsuit', 'Reign Fashion ready-to-wear: Burgundy tailored peplum jumpsuit with draped shoulder sash and straight trousers', 'ready-to-wear', 'tall', 'assets/images/ready-to-wear/ready-to-wear-burgundy-jumpsuit.jpg', 535, 1024, '50% 15%', 0, 1, 30],
        [4, 'Royal Velvet Kaftan', 'Reign Fashion menswear: royal blue velvet kaftan with an embroidered neckline and matching cap', 'men', 'tall', 'assets/images/men/men-royal-velvet.webp', 735, 909, '50% 20%', 0, 1, 40],
        [5, 'Royal Isiagu Ceremonial Regalia', 'Reign Fashion kiddies: Young boy in royal burgundy Isiagu outfit with red cap, coral beads and ceremonial fan', 'kids', 'tall', 'assets/images/kids/kids-royal-isiagu.jpg', 507, 604, '50% 15%', 0, 1, 50],
        [6, 'Regal Purple Brocade Corset', 'Reign Fashion Aso-Ebi: Regal purple corset gown with floral brocade sequins and sculptured purple gele', 'aso-ebi', 'tall', 'assets/images/aso-ebi/aso-ebi-regal-purple.jpg', 1080, 1350, '50% 15%', 0, 1, 60],
        [7, 'Feather Ball Gown', 'Reign Fashion bridal: off-shoulder ball gown with feather sleeves and sequinned embroidery', 'bridal', 'tall', 'assets/images/bridal/gown-feather-ballgown.webp', 1200, 1500, '50% 30%', 0, 1, 70],
        [8, 'Sky Blue Draped Mini Dress', 'Reign Fashion ready-to-wear: Sky blue tailored mini dress with draped satin sash and flap pockets', 'ready-to-wear', 'tall', 'assets/images/ready-to-wear/ready-to-wear-sky-blue-dress.jpg', 736, 870, '50% 20%', 0, 1, 80],
        [32, 'Burnt Orange Fluted-Sleeve Midi Dress', 'Reign Fashion ready-to-wear: Burnt orange structured pencil midi dress with dramatic fluted sleeves and statement collar', 'ready-to-wear', 'tall', 'assets/images/ready-to-wear/ready-to-wear-burnt-orange-midi.jpg', 483, 635, '50% 20%', 0, 1, 82],
        [33, 'Terracotta Bow-Accent Flare Dress', 'Reign Fashion ready-to-wear: Terracotta puff-sleeve A-line dress with side bow accents and flared skirt', 'ready-to-wear', 'tall', 'assets/images/ready-to-wear/ready-to-wear-terracotta-bow-dress.jpg', 335, 597, '50% 15%', 0, 1, 84],
        [9, 'White Embroidered Agbada', 'Reign Fashion menswear: white agbada with an ornate embroidered front panel', 'men', 'tall', 'assets/images/men/men-white-agbada.webp', 735, 916, '50% 25%', 0, 1, 90],
        [10, 'Ankara & Lace Party Ballgown', 'Reign Fashion kiddies: Little girl Ankara print dress layered with white floral lace overlay and ribbon waist bow', 'kids', 'tall', 'assets/images/kids/kids-ankara-lace-dress.jpg', 720, 886, '50% 25%', 0, 1, 100],
        [11, 'Pearl Mermaid & Veil', 'Reign Fashion bridal: strapless pearl-encrusted mermaid gown with a full tulle skirt and pearl-edged veil', 'bridal', 'tall', 'assets/images/bridal/gown-pearl-mermaid-veil.webp', 1200, 1500, '50% 30%', 0, 1, 110],
        [13, 'Architectural Gold Mirrored Gown', 'Reign Fashion Aso-Ebi: Architectural imperial gold mirrored gown with 3D leaf shoulder wings on staircase', 'aso-ebi', 'tall', 'assets/images/aso-ebi/aso-ebi-gold-sculpture.jpg', 1080, 1920, '50% 15%', 0, 1, 120],
        [23, 'Emerald Beaded Royal Corset', 'Reign Fashion Aso-Ebi: Royal emerald green beaded corset gown with slit, matching hand fan and emerald gele', 'aso-ebi', 'tall', 'assets/images/aso-ebi/aso-ebi-emerald-corset.jpg', 1080, 1920, '50% 15%', 0, 1, 125],
        [27, 'Scarlet Crystal & Silver Bodice Gown', 'Reign Fashion Aso-Ebi: Scarlet red lace gown with silver crystal bodice, sheer sleeves and sculpted crimson gele', 'aso-ebi', 'tall', 'assets/images/aso-ebi/aso-ebi-crimson-silver.jpg', 495, 619, '50% 15%', 0, 1, 126],
        [28, 'Ruby Beaded Off-Shoulder Gown', 'Reign Fashion Aso-Ebi: Ruby red beaded off-shoulder gown with matching hand fan and sculpted ruby gele', 'aso-ebi', 'tall', 'assets/images/aso-ebi/aso-ebi-ruby-corset.jpg', 399, 501, '50% 15%', 0, 1, 127],
        [29, 'Bronze Sequinned Luxury Corset', 'Reign Fashion Aso-Ebi: Bronze and chocolate beaded corset gown with ornate neckline and pleated gold gele', 'aso-ebi', 'tall', 'assets/images/aso-ebi/aso-ebi-bronze-sequin.jpg', 819, 1024, '50% 15%', 0, 1, 128],
        [30, 'Lilac Rose Lace with Feather Cuffs', 'Reign Fashion Aso-Ebi: Lilac rose lace gown with crystal neckline, dramatic feather cuffs and royal purple gele', 'aso-ebi', 'tall', 'assets/images/aso-ebi/aso-ebi-lilac-feather.jpg', 452, 678, '50% 15%', 0, 1, 129],
        [17, 'Lilac Kaftan Set', 'Reign Fashion menswear: lilac kaftan with purple placket trim and matching trousers', 'men', 'tall', 'assets/images/men/men-lilac-kaftan.webp', 720, 959, '50% 30%', 0, 1, 130],
        [31, 'Espresso Beaded Corset & Turban', 'Reign Fashion Aso-Ebi: Espresso brown structured corset gown with sheer sleeves and matching turban', 'aso-ebi', 'tall', 'assets/images/aso-ebi/aso-ebi-espresso-corset.jpg', 480, 640, '50% 20%', 0, 1, 131],
        [18, 'White Tan-Trim Kaftan', 'Reign Fashion menswear: white long-sleeve kaftan with tan trim and buttons', 'men', 'tall', 'assets/images/men/men-white-tan-trim.webp', 736, 981, '50% 25%', 0, 1, 140],
        [19, 'Embellished White Kaftan', 'Reign Fashion menswear: white kaftan with an embellished placket and embroidered pocket', 'men', 'tall', 'assets/images/men/men-white-embellished.webp', 651, 1024, '50% 25%', 0, 1, 150],
        [24, 'Powder Blue Two-Piece', 'Reign Fashion menswear: powder-blue sleeveless tunic with pockets and matching trousers', 'men', 'tall', 'assets/images/men/men-powder-blue.webp', 736, 1470, '50% 25%', 0, 1, 152],
        [25, 'Geometric Pocket Kaftan', 'Reign Fashion menswear: white kaftan with a geometric embroidered pocket and striped cap', 'men', 'tall', 'assets/images/men/men-white-geometric.webp', 736, 1305, '50% 20%', 0, 1, 154],
        [26, 'Embroidered Pocket Kaftans', 'Reign Fashion menswear: white kaftans with embroidered pockets and a striped cap', 'men', 'tall', 'assets/images/men/men-white-pocket-duo.webp', 720, 872, '50% 30%', 0, 1, 156],
        [20, 'Royal Blue Senator Two-Piece', 'Reign Fashion kiddies: Boy in royal blue short-sleeve senator suit with sleeve cutouts and purple bead necklace', 'kids', 'tall', 'assets/images/kids/kids-royal-blue-senator.jpg', 576, 1024, '50% 15%', 0, 1, 160],
        [21, 'Tiered African Print Flare Dress', 'Reign Fashion kiddies: Young girl in yellow, green and red geometric African print flared dress with tiered skirt', 'kids', 'tall', 'assets/images/kids/kids-tiered-african-print.jpg', 235, 350, '50% 15%', 0, 1, 170],
        [22, 'One-Shoulder Ankara Rosette Dress', 'Reign Fashion kiddies: Toddler girl one-shoulder Ankara dress with navy floral lace trims and waist rosette flower', 'kids', 'tall', 'assets/images/kids/kids-one-shoulder-rosette.jpg', 810, 997, '50% 20%', 0, 1, 180],
    ];

    $pdo->exec('TRUNCATE TABLE `gallery_items`');

    $ins = $pdo->prepare('INSERT INTO `gallery_items` (`id`, `title`, `alt`, `category`, `size`, `src`, `width`, `height`, `focus`, `is_sample`, `is_visible`, `sort_order`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    foreach ($items as $row) {
        $ins->execute($row);
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Successfully synchronized all 29 designs into gallery_items!',
        'count' => count($items)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
