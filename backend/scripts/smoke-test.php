<?php
declare(strict_types=1);

/**
 * HTTP smoke test against a running server (read-only apart from rejected requests).
 *   php scripts/smoke-test.php http://localhost:8000
 */
$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$pass = 0;
$fail = 0;
function call(string $method, string $url, array $opts = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 15] + $opts);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$code, substr($raw, 0, $hs), json_decode(substr($raw, $hs), true)];
}
$check = static function (string $label, bool $ok) use (&$pass, &$fail): void {
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . "\n";
};

[$c, , $j] = call('GET', "$base/api/health");
$check('GET /api/health → 200 and database up', $c === 200 && ($j['database'] ?? '') === 'up');
[$c, $h, $j] = call('GET', "$base/api/display/current");
$check('GET /api/display/current → 200 with version', $c === 200 && isset($j['data']['version']));
$check('display state is not cacheable', stripos($h, 'no-store') !== false);
$check('display state exposes no phone', !str_contains(json_encode($j), 'phone'));
[$c] = call('GET', "$base/api/admin/visitors");
$check('admin visitors without session → 401', $c === 401);
[$c] = call('GET', "$base/api/admin/settings");
$check('admin settings without session → 401', $c === 401);
[$c] = call('POST', "$base/api/admin/gallery");
$check('admin gallery POST without session → 401', $c === 401);
[$c] = call('GET', "$base/api/media/visitor/000000000000000000000000");
$check('unknown visitor photo → 404', $c === 404);
[$c, , $j] = call('POST', "$base/api/visitors", [CURLOPT_POSTFIELDS => ['name' => '', 'phone' => 'abc']]);
$check('empty registration → 422 (or 403 if closed)', in_array($c, [422, 403], true));
$tmp = tempnam(sys_get_temp_dir(), 'vms') . '.php';
file_put_contents($tmp, '<?php echo "x";');
[$c] = call('POST', "$base/api/visitors", [CURLOPT_POSTFIELDS => [
    'name' => 'Test User', 'phone' => '9876543210', 'consent' => '1', 'photo' => new CURLFile($tmp, 'image/jpeg', 'x.jpg')]]);
$check('PHP file disguised as JPEG is rejected', in_array($c, [422, 403], true));
@unlink($tmp);
[$c] = call('GET', "$base/api/nope");
$check('unknown endpoint → 404', $c === 404);
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
