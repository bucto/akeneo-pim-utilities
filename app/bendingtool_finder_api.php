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

$attrs = array_unique(array_merge(
    array_map('trim', explode(',', PIM_BENDING_LENGTH_ATTRS)),
    array_map('trim', explode(',', PIM_BENDING_RADIUS_ATTRS)),
    ['product_name']
));

$products = getAkeneoProductsByParent($modelCode, $attrs);

if (empty($products) && getLastApiError()) {
    http_response_code(502);
    echo json_encode(['error' => getLastApiError()]);
    exit;
}

$variants = array_map(function (array $product): array {
    $enabled = ($product['enabled'] ?? true) !== false;
    $length  = extractAttrValueFirst($product, PIM_BENDING_LENGTH_ATTRS);
    $radius  = extractBendingShoulderRadius($product);

    return [
        'identifier' => $product['identifier'] ?? '',
        'name'       => extractProductName($product) ?? ($product['identifier'] ?? ''),
        'length'     => $length,
        'radius'     => $radius,
        'status'     => [
            'display' => $enabled ? 'Aktiv' : 'Inaktiv',
            'raw'     => $enabled ? 'active' : 'disabled',
        ],
    ];
}, $products);

echo json_encode([
    'model'    => $modelCode,
    'variants' => $variants,
]);
