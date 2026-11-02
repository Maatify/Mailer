<?php

// Writes a self-signed certificate and key (valid for 127.0.0.1) to <pem-file>, for the local
// SMTP stand-in of the consumer harness.
// Usage: php certificate.php <pem-file>

$arguments = $_SERVER['argv'] ?? [];
$pem = is_array($arguments) && isset($arguments[1]) && is_string($arguments[1]) ? $arguments[1] : '';
if ($pem === '') {
    fwrite(STDERR, "Usage: php certificate.php <pem-file>\n");
    exit(2);
}

$config = $pem . '.cnf';
file_put_contents($config, "[req]\ndistinguished_name=dn\nx509_extensions=v3\nprompt=no\n[dn]\nCN=127.0.0.1\n[v3]\nsubjectAltName=IP:127.0.0.1\nbasicConstraints=CA:TRUE\n");
$options = ['config' => $config, 'x509_extensions' => 'v3', 'req_extensions' => 'v3', 'private_key_bits' => 2048];
$key = openssl_pkey_new($options);
if ($key === false) {
    fwrite(STDERR, "Could not generate the key\n");
    exit(1);
}
$requestKey = $key; // openssl_csr_new() takes the key by reference
$csr = openssl_csr_new(['commonName' => '127.0.0.1'], $requestKey, $options);
if (!$csr instanceof OpenSSLCertificateSigningRequest) {
    fwrite(STDERR, "Could not create the certificate request\n");
    exit(1);
}
$cert = openssl_csr_sign($csr, null, $key, 1, $options);
if ($cert === false) {
    fwrite(STDERR, "Could not sign the certificate\n");
    exit(1);
}
openssl_x509_export($cert, $certPem);
openssl_pkey_export($key, $keyPem, null, $options);
file_put_contents($pem, (is_string($certPem) ? $certPem : '') . (is_string($keyPem) ? $keyPem : ''));
unlink($config);
