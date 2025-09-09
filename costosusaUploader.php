<?php
// CostosUploader.php - Importador de costos desde CSV (no XLSX) usando mysqli ($conn2)
// - Tabla destino: costosusa
// - Fechas CSV: d/m/Y o j/n/Y (con o sin hora) -> guardado Y-m-d
// - Decimales: normalizados a DECIMAL(20,5)
// - UI: intenta usar header/footer del proyecto; si no existen, usa fallback con Bootstrap/FA

require_once __DIR__ . '/db.php'; // Debe definir $conn2 (mysqli)

@ini_set('memory_limit', '512M');
@set_time_limit(0);

// ========================= UI HELPERS (estilos del proyecto) =========================
function include_if_exists(array $paths): bool
{
    foreach ($paths as $p) {
        if (file_exists($p)) {
            include $p;
            return true;
        }
    }
    return false;
}
function view_header(string $title = 'Importar Costos CSV'): void
{
    // Intenta header del proyecto
    $ok = include_if_exists([
        __DIR__ . '/header.php',
        __DIR__ . '/includes/header.php',
        __DIR__ . '/partials/header.php',
    ]);
    if (!$ok) {
        // Fallback mínimo (para no ver texto plano)
        echo '<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . htmlspecialchars($title) . '</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head><body>';
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-success"><div class="container"><span class="navbar-brand">Whangai</span></div></nav>';
    }
}
function view_footer(): void
{
    // Intenta footer del proyecto
    $ok = include_if_exists([
        __DIR__ . '/footer.php',
        __DIR__ . '/includes/footer.php',
        __DIR__ . '/partials/footer.php',
    ]);
    if (!$ok) {
        // Fallback mínimo
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo '</body></html>';
    }
}

// ========================= CONFIG =========================
$TABLE_NAME = 'costosusa';
$COLUMN_RENAME = ['Clasificación' => 'Clasificacion', 'Débito' => 'Debito', 'Crédito' => 'Credito'];
$DECIMAL_COLS = ['Debito', 'Credito'];
$INTEGER_LIKE_COLS = ['Numero_Documento', 'Codigo_Cuenta'];
$DATE_COLS = ['Fecha'];

// ========================= HELPERS =========================
function normalize_encoding_to_utf8(string $path): string
{
    $sample = @file_get_contents($path, false, null, 0, 8192);
    $enc = ($sample && function_exists('mb_detect_encoding'))
        ? mb_detect_encoding($sample, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true)
        : 'UTF-8';
    if ($enc && $enc !== 'UTF-8') {
        $tmp = tempnam(sys_get_temp_dir(), 'csvu8_');
        $in = fopen($path, 'r');
        $out = fopen($tmp, 'w');
        while (!feof($in)) {
            $chunk = fread($in, 1048576);
            if ($chunk === false) break;
            $chunk = iconv($enc, 'UTF-8//TRANSLIT', $chunk);
            fwrite($out, $chunk);
        }
        fclose($in);
        fclose($out);
        return $tmp;
    }
    return $path;
}
function detect_delimiter_from_first_line(string $path): string
{
    $fh = fopen($path, 'r');
    $first = fgets($fh);
    fclose($fh);
    if ($first === false) return ';';
    $cands = [',', ';', '|', "\t"];
    $best = ';';
    $max = -1;
    foreach ($cands as $d) {
        $c = substr_count($first, $d);
        if ($c > $max) {
            $max = $c;
            $best = $d;
        }
    }
    return $max > 0 ? $best : ';';
}
function sanitize_header(string $h): string
{
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
    $h = trim($h);
    $h = preg_replace('/\s+/', '_', $h);
    return $h;
}
function normalize_decimal_string($val, int $scale = 5): ?string
{
    if ($val === null) return null;
    $s = trim((string)$val);
    if ($s === '') return null;
    if (preg_match('/^\-?\d{1,3}(\.\d{3})*(,\d+)?$/', $s)) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^\-?\d{1,3}(,\d{3})*(\.\d+)?$/', $s)) {
        $s = str_replace(',', '', $s);
    }
    $s = preg_replace('/[^0-9\.\-]/', '', $s);
    if ($s === '' || !preg_match('/^\-?\d+(\.\d+)?$/', $s)) return null;
    if (strpos($s, '.') === false) return $scale > 0 ? $s . '.' . str_repeat('0', $scale) : $s;
    [$int, $dec] = explode('.', $s, 2);
    $dec = substr($dec, 0, $scale);
    return $int . '.' . str_pad($dec, $scale, '0');
}
// d/m/Y o j/n/Y (con o sin hora) -> Y-m-d
function parse_date_dmy($val): ?string
{
    if (!$val) return null;
    $s = trim((string)$val);
    if ($s === '') return null;
    $s = str_replace('-', '/', $s);
    $re = '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM|am|pm)?)?\s*$/';
    if (!preg_match($re, $s, $m)) {
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : null;
    }
    $d = (int)$m[1];
    $mth = (int)$m[2];
    $y = (int)$m[3];
    if (!checkdate($mth, $d, $y)) return null;
    return sprintf('%04d-%02d-%02d', $y, $mth, $d);
}
function insert_batch_mysqli(mysqli $db, string $table, array $rows): int
{
    if (empty($rows)) return 0;
    $cols = array_keys($rows[0]);
    $colList = '`' . implode('`,`', $cols) . '`';
    $valsSQL = [];
    foreach ($rows as $r) {
        $vals = [];
        foreach ($cols as $c) {
            $v = $r[$c] ?? null;
            $vals[] = ($v === null || $v === '') ? 'NULL' : "'" . $db->real_escape_string($v) . "'";
        }
        $valsSQL[] = '(' . implode(',', $vals) . ')';
    }
    $sql = "INSERT INTO `{$table}` ({$colList}) VALUES " . implode(',', $valsSQL);
    $db->begin_transaction();
    $ok = $db->query($sql);
    if (!$ok) {
        $err = $db->error;
        $db->rollback();
        http_response_code(500);
        die("Error insertando datos: " . $err);
    }
    $db->commit();
    return count($rows);
}

// ========================= POST (procesamiento) =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($conn2) || !($conn2 instanceof mysqli)) {
        http_response_code(500);
        die("No se encontró la conexión mysqli \$conn2.");
    }
    $conn2->set_charset('utf8mb4');

    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        view_header('Importar Costos CSV');
        echo '<div class="container mt-4"><div class="alert alert-danger">Error subiendo el archivo.</div></div>';
        view_footer();
        exit;
    }

    $originalName = $_FILES['csv']['name'] ?? '';
    $ext = $originalName !== '' ? strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) : '';
    if ($ext && $ext !== 'csv') {
        view_header('Importar Costos CSV');
        echo '<div class="container mt-4"><div class="alert alert-warning">Extensión inválida. Sube un archivo .csv</div></div>';
        view_footer();
        exit;
    }
    if ($ext === '') {
        $mime = @mime_content_type($_FILES['csv']['tmp_name']);
        if ($mime && stripos($mime, 'csv') === false && stripos($mime, 'text') === false) {
            view_header('Importar Costos CSV');
            echo '<div class="container mt-4"><div class="alert alert-warning">Archivo inválido. Sube un .csv</div></div>';
            view_footer();
            exit;
        }
    }

    // Guardar archivo
    $uploads = __DIR__ . '/uploads';
    if (!is_dir($uploads)) {
        mkdir($uploads, 0755, true);
    }
    $dest = $uploads . '/' . uniqid('costos_', true) . '.csv';
    if (!move_uploaded_file($_FILES['csv']['tmp_name'], $dest)) {
        view_header('Importar Costos CSV');
        echo '<div class="container mt-4"><div class="alert alert-danger">No se pudo guardar el archivo en el servidor.</div></div>';
        view_footer();
        exit;
    }

    // Lectura
    $utf8 = normalize_encoding_to_utf8($dest);
    $tmpCreated = ($utf8 !== $dest);
    $delimiter = detect_delimiter_from_first_line($utf8);

    $f = new SplFileObject($utf8, 'r');
    $f->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
    $f->setCsvControl($delimiter, '"', '\\');

    $headers = null;
    $buffer = [];
    $inserted = 0;
    $BATCH = 300;

    foreach ($f as $row) {
        if ($row === [null] || $row === false) continue;

        if ($headers === null) {
            $headers = array_map('sanitize_header', $row);
            foreach ($headers as $i => $h) {
                if (isset($COLUMN_RENAME[$h])) $headers[$i] = $COLUMN_RENAME[$h];
                else {
                    $noAcc = strtr($h, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
                    if (isset($COLUMN_RENAME[$noAcc])) $headers[$i] = $COLUMN_RENAME[$noAcc];
                }
            }
            continue;
        }

        if (count($row) < count($headers)) $row = array_pad($row, count($headers), null);
        $assoc = array_combine($headers, array_map(fn($v) => is_string($v) ? trim($v) : $v, $row));

        // Fechas
        foreach ($DATE_COLS as $dc) if (array_key_exists($dc, $assoc)) $assoc[$dc] = parse_date_dmy($assoc[$dc]);
        // Decimales
        foreach ($DECIMAL_COLS as $dc) if (array_key_exists($dc, $assoc)) $assoc[$dc] = normalize_decimal_string($assoc[$dc], 5);
        // Enteros/códigos
        foreach ($INTEGER_LIKE_COLS as $ic) if (array_key_exists($ic, $assoc) && $assoc[$ic] !== null) {
            $s = preg_replace('/[^0-9\-]/', '', (string)$assoc[$ic]);
            $assoc[$ic] = ($s === '' ? null : $s);
        }

        $buffer[] = $assoc;
        if (count($buffer) >= $BATCH) {
            $inserted += insert_batch_mysqli($conn2, $TABLE_NAME, $buffer);
            $buffer = [];
        }
    }
    if (!empty($buffer)) $inserted += insert_batch_mysqli($conn2, $TABLE_NAME, $buffer);
    if ($tmpCreated && is_file($utf8)) {
        @unlink($utf8);
    }
    // @unlink($dest);

    // Vista de éxito con estilos del proyecto
    view_header('Importar Costos CSV');
    echo '
    <div class="container mt-4">
      <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
          <i class="fas fa-check-circle me-2"></i> Importación de Costos
        </div>
        <div class="card-body">
          <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <div><strong>Proceso completado.</strong> Filas insertadas: <strong>' . (int)$inserted . '</strong></div>
          </div>
          <a href="index.php" class="btn btn-outline-success">
            <i class="fas fa-arrow-left me-1"></i> Regresar
          </a>
        </div>
      </div>
    </div>';
    view_footer();
    exit;
}

// ========================= GET (formulario) =========================
view_header('Importar Costos CSV');
?>
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <i class="fas fa-upload me-2"></i> Subir Archivo CSV - Costos USA
        </div>
        <div class="card-body">
            <div class="alert alert-info" role="alert">
                <div class="d-flex">
                    <div class="me-3"><i class="fas fa-info-circle fa-lg"></i></div>
                    <div>
                        <strong>Instrucciones:</strong>
                        <ul class="mb-0">
                            <li>Selecciona un archivo <strong>CSV</strong> con los datos de costos.</li>
                            <li>La primera fila debe contener los <strong>encabezados</strong>.</li>
                            <li>Las fechas pueden venir como <code>d/m/Y</code> o <code>j/n/Y</code> (con o sin hora);
                                se guardan como <code>YYYY-mm-dd</code>.</li>
                            <li>Los campos <em>Débito</em> y <em>Crédito</em> se convierten a
                                <code>DECIMAL(20,5)</code>.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-file-csv me-1"></i> Seleccionar archivo CSV de Costos
                    </label>
                    <input class="form-control" type="file" name="csv" accept=".csv,text/csv" required>
                    <div class="form-text text-success">
                        <i class="fas fa-check-circle me-1"></i> Separador autodetectado (coma, punto y coma, tab).
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-cloud-upload-alt me-1"></i> Subir y Procesar CSV
                    </button>
                    <a href="updater.php" class="btn btn-outline-success">
                        <i class="fas fa-arrow-left me-1"></i> Regresar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php view_footer(); ?>