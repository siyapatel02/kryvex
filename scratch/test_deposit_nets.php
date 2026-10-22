<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$userUid = 'ee57787c-786c-4378-8659-f3f8db984bb1';
$controller = app(\App\Http\Controllers\Api\WalletController::class);

$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('depositNetworks');
$method->setAccessible(true);
$res = $method->invoke($controller, $userUid);

echo "depositNetworks for user:\n";
echo json_encode($res, JSON_PRETTY_PRINT) . "\n";
