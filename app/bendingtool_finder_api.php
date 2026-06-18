<?php
include('api_helper.php');
include('common.php');

header('Content-Type: application/json; charset=utf-8');

$modelCode = trim($_GET['model'] ?? '');
if ($modelCode === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Modellcode fehlt.']);
    exit;
}

// Ohne Attribut-Filter laden — ungültige Attribute würden sonst 422 auslösen.
$products = getAkeneoProductsByParent($modelCode, []);

if (empty($products) && getLastApiError()) {
    http_response_code(502);
    echo json_encode(['error' => getLastApiError()]);
    exit;
}

$lengthAttr  = trim(explode(',', PIM_BENDING_LENGTH_ATTRS)[0]);
$lengthOpts  = getAkeneoAttributeOptionLabels($lengthAttr);

$enriched = array_map(
    fn(array $product) => mergeProductWithAncestorValues($product),
    $products
);
$enriched = sortBendingVariantProducts($enriched);

$variants = array_map(function (array $product) use ($lengthOpts): array {
    $enabled = ($product['enabled'] ?? true) !== false;

    return [
        'identifier' => $product['identifier'] ?? '',
        'name'       => extractProductName($product) ?? ($product['identifier'] ?? ''),
        'sapNumber'  => extractAttrValueFirst($product, PIM_BENDING_SAP_ATTRS),
        'length'     => extractAttrValueFirstWithOptions($product, PIM_BENDING_LENGTH_ATTRS, $lengthOpts),
        'radius'     => extractBendingShoulderRadius($product),
        'status'     => [
            'display' => $enabled ? 'Aktiv' : 'Inaktiv',
            'raw'     => $enabled ? 'active' : 'disabled',
        ],
    ];
}, $enriched);

echo json_encode([
    'model'    => $modelCode,
    'variants' => $variants,
]);
