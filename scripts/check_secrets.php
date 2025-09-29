<?php
// scripts/check_secrets.php
// Comprueba los archivos en staging y evita commitear .env y otros archivos sensibles.

$output = [];
// Obtener lista de archivos staged
exec('git diff --cached --name-only', $output, $exitCode);
if ($exitCode !== 0) {
    fwrite(STDERR, "No se pudo obtener la lista de archivos staged.\n");
    exit(1);
}

$blockedPaths = [
    '.env',
    'n8n/.env',
    'n8n/n8n_data',
];

$blockedPatterns = [
    '/(^|\\/)\.env$/i',
    '/\\.pem$/i',
    '/(^|\\/)n8n(\\/|$)/i',
];

$found = [];
foreach ($output as $file) {
    $file = trim($file);
    if ($file === '') continue;

    // direct blocked paths
    foreach ($blockedPaths as $bp) {
        if (strcasecmp($file, $bp) === 0) {
            $found[] = $file;
        }
    }

    // patterns
    foreach ($blockedPatterns as $pat) {
        if (preg_match($pat, $file)) {
            $found[] = $file;
        }
    }

    // additionally scan staged content for common secret keys
    $stagedContent = null;
    $cmd = "git show :" . escapeshellarg($file);
    exec($cmd, $lines, $c2);
    if ($c2 === 0) {
        $stagedContent = implode("\n", $lines);
        $secretPatterns = [
            '/API[_-]?KEY\s*=/i',
            '/CLIENT[_-]?SECRET\s*=/i',
            '/PRIVATE[_-]?KEY\s*=/i',
            '/PASSWORD\s*=/i',
            '/GEMINI_API_KEY\s*=/i',
        ];
        foreach ($secretPatterns as $sp) {
            if (preg_match($sp, $stagedContent)) {
                $found[] = $file . ' (contains potential secret)';
                break;
            }
        }
    }
}

if (!empty($found)) {
    fwrite(STDERR, "ERROR: Se están intentando commitear archivos que pueden contener secretos:\n");
    foreach (array_unique($found) as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(STDERR, "\nPor favor elimina esos archivos del staging (git rm --cached <file>) o mueve las variables sensibles a tu gestor de secretos.\n");
    exit(1);
}

// OK
exit(0);
