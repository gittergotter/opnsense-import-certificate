<?php
/**
 * Import SSL certificates from a pre-determined place on the filesystem.
 * Once imported, set them for use in the GUI.
 *
 * Re-running with a renewed certificate REPLACES the previous entry created
 * by this script (matched on description prefix), keeping the same refid so
 * the webgui reference never changes and old certificates do not accumulate.
 */
if (empty($argc)) {
        echo "Only accessible from the CLI.\r\n";
        die(1);
}
if ($argc != 4) {
        echo "Usage: php " . $argv[0] . " /path/to/certificate.crt /path/to/private/key.pem cert_hostname.domain.tld\r\n";
        die(1);
}

require_once "config.inc";
require_once "certs.inc";
require_once "util.inc";

$descr_prefix = "Certificate added to opnsense through ";

$certificate_raw = file_get_contents($argv[1]);
if ($certificate_raw === false) {
        echo "Could not read certificate file: " . $argv[1] . "\r\n";
        die(1);
}
$key_raw = file_get_contents($argv[2]);
if ($key_raw === false) {
        echo "Could not read key file: " . $argv[2] . "\r\n";
        die(1);
}
$certificate = trim($certificate_raw);
$key = trim($key_raw);
$hostname = trim($argv[3]);

// Quick verification of the certificate, similar to what the GUI does
if (empty($certificate)) {
        echo "The certificate is empty.\r\n";
        die(1);
}
if (!strstr($certificate, "BEGIN CERTIFICATE") || !strstr($certificate, "END CERTIFICATE")) {
        echo "This certificate does not appear to be valid.\r\nOr: cert and privkey args switched?\r\n";
        die(1);
}
if (empty($key)) {
        echo "The key is empty.\r\n";
        die(1);
}
if (!strstr($key, "PRIVATE KEY")) {
        echo "This key does not appear to be valid.\r\nOr: cert and privkey args switched?\r\n";
        die(1);
}
// Verify that the private key actually matches the certificate
if (!openssl_x509_check_private_key($certificate, $key)) {
        echo "The private key does not match the certificate.\r\n";
        die(1);
}

// Set up the existing certificate store
if (!is_array($config['cert'])) {
        $config['cert'] = array();
}
$a_cert =& $config['cert'];

$cert = array();
$cert['refid'] = uniqid();
$cert['descr'] = $descr_prefix . basename($argv[0]) . " on " . date("Y/m/d");
cert_import($cert, $certificate, $key);

// Already imported? (compare the certificate portion)
foreach ($a_cert as $existing_cert) {
        if (isset($existing_cert['crt']) && $existing_cert['crt'] === $cert['crt']) {
                echo "The certificate is already imported.\r\n";
                die(); // exit with a valid error code, as this is intended behaviour
        }
}

// Replace a previous entry created by this script (keep its refid), else append
$replaced = false;
foreach ($a_cert as $idx => $existing_cert) {
        if (isset($existing_cert['descr']) && strpos($existing_cert['descr'], $descr_prefix) === 0) {
                $cert['refid'] = $existing_cert['refid'];
                $a_cert[$idx] = $cert;
                $replaced = true;
                break;
        }
}
if (!$replaced) {
        $a_cert[] = $cert;
}

// Point the GUI at the certificate and write everything out in one go
$config['system']['webgui']['ssl-certref'] = $cert['refid'];
if (!is_array(write_config("Imported certificate for " . $hostname . " via " . basename($argv[0])))) {
        echo "Could not save the configuration.\r\n";
        die(1);
}

log_error('Web GUI configuration has changed. Restarting now.');
configd_run('webgui restart 2', true);
echo "Completed! New certificate " . ($replaced ? "replaced previous entry" : "installed") . ".\r\n";
