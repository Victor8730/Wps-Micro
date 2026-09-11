<?php

declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php tools/check-coverage.php <clover.xml> <minimum-percent>\n");

    exit(2);
}

$path = $argv[1];
$minimum = filter_var($argv[2], FILTER_VALIDATE_FLOAT);

if (!is_string($path) || $path === '' || !is_file($path) || $minimum === false) {
    fwrite(STDERR, "A readable Clover report and numeric minimum percentage are required.\n");

    exit(2);
}

$document = new DOMDocument();

if (!$document->load($path)) {
    fwrite(STDERR, "Unable to read Clover report: {$path}\n");

    exit(2);
}

$xpath = new DOMXPath($document);
$metrics = $xpath->query('/coverage/project/metrics')->item(0);

if (!$metrics instanceof DOMElement) {
    fwrite(STDERR, "Clover report does not contain project metrics.\n");

    exit(2);
}

$statements = (int) $metrics->getAttribute('statements');
$coveredStatements = (int) $metrics->getAttribute('coveredstatements');
$coverage = $statements === 0 ? 100.0 : ($coveredStatements / $statements) * 100;

printf("Statement coverage: %.2f%% (required: %.2f%%)\n", $coverage, $minimum);

exit($coverage + 0.00001 >= $minimum ? 0 : 1);
