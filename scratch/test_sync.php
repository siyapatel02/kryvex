<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::connection('mongodb')->table('user_kyc_verifications')->where('user_uid', 'ee57787c-786c-4378-8659-f3f8db984bb1')->update([
    'digio_request_id' => 'KID260724121710774I2WX2IYFB5VY5O'
]);

$ctrl = new App\Http\Controllers\Admin\AdminKycController();
$record = DB::connection('mongodb')->table('user_kyc_verifications')->where('user_uid', 'ee57787c-786c-4378-8659-f3f8db984bb1')->first();

$reflection = new ReflectionClass($ctrl);
$method = $reflection->getMethod('syncDigioDataIfNeeded');
$method->setAccessible(true);
$res = $method->invoke($ctrl, 'ee57787c-786c-4378-8659-f3f8db984bb1', $record);

echo "--- UPDATED RECORD IN DB ---\n";
print_r($res);
