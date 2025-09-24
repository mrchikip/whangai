<?php
// ==============================
// costosUploader.php (XLSX, Costos COL) - UI igual a costosusaUploader
// - Encabezados: SIEMPRE la fila 1 del XLSX.
// - Mapeo por nombre (sin tildes/espacios; case-insensitive).
// - Vacíos -> NULL.
// - Fecha -> YYYY-MM-DD (acepta DD/MM/YYYY o serial Excel).
// - Débito/Crédito: limpia $, miles, "$-", paréntesis negativos; hasta 5 dec.
// - Fix anti-corrimiento:
//   * (1) Centro_de_Costos con solo dígitos y Ident vacío -> mover a Ident.
//   * (2) prefijo con solo dígitos y Numero_Documento vacío -> mover a Numero.
//   * (3) Clasificacion con solo dígitos (3–8) y Codigo_Cuenta vacío -> mover a Codigo_Cuenta.
//   * (4) Centro & Ident vacíos y Concepto trae ID (6–15 dígitos) -> mover a Ident y vaciar Concepto.
// - SIN escape HTML al guardar (texto 1:1 al Excel).
// - Usa $conn2 y tabla costoscol.
// ==============================

include("db.php");
include("includes/auth.php");

define('MAX_FILE_SIZE', 40 * 1024 * 1024); // 40MB
define('ALLOWED_EXTENSIONS', ['xlsx']);
define('DB_TABLE', 'costoscol'); // <<< cambia si tu tabla COL tiene otro nombre

const DB_FIELDS = [
    'Empresa',
    'Tipo_Documento',
    'Nombre_Documento',
    'prefijo',
    'Numero_Documento',
    'Fecha',
    'Tercero',
    'Clasificacion',
    'Codigo_Cuenta',
    'Nombre_Cuenta',
    'Debito',
    'Credito',
    'Concepto_Detalle',
    'Centro_de_Costos',
    'Identificacion_Tercero'
];

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// ---------- Normalizadores ----------
function has_mb(){ return function_exists('mb_strtolower'); }
function tolower_utf8($s){ return has_mb()?mb_strtolower($s,'UTF-8'):strtolower($s); }
function removeAccents($s){
    $map=['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','Ü'=>'U',
          'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u'];
    return strtr((string)$s,$map);
}
function normKey($h){
    $h = trim((string)$h);
    $h = removeAccents($h);
    $h = tolower_utf8($h);
    $h = preg_replace('/[^\p{L}\p{N}]+/u','_',$h);
    $h = preg_replace('/_+/','_',$h);
    return trim($h,'_');
}

// ---------- Fechas / montos ----------
function excelSerialToYmd($n) {
    if (!is_numeric($n)) return null;
    $unix = ((int)$n - 25569) * 86400; // base 1900
    if ($unix <= 0) return null;
    return gmdate('Y-m-d', $unix);
}
function toDateYmdOrNull($raw) {
    if ($raw === null || $raw === '') return null;
    if (is_numeric($raw)) { $d = excelSerialToYmd($raw); return $d ?: null; }
    $s = trim((string)$raw);
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/',$s,$m)){
        $d=(int)$m[1]; $M=(int)$m[2]; $y=(int)$m[3];
        return checkdate($M,$d,$y) ? sprintf('%04d-%02d-%02d',$y,$M,$d) : null;
    }
    if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/',$s)) return $s;
    return null;
}
function cleanMoneyString($s) {
    // elimina símbolos moneda, NBSP, tabs, espacios
    $repls = ["\xc2\xa0", "\u{00A0}", " ", "\t", "\r", "\n", "$", "COP", "USD"];
    return trim(str_replace($repls, "", $s));
}
function toDecimalOrNull($raw) {
    if ($raw === null) return null;
    $s = (string)$raw;
    if ($s === '') return null;

    // normaliza espacios/moneda
    $s = cleanMoneyString($s);

    // placeholders vacíos
    if ($s === '' || $s === '-' || $s === '--' || $s === '$-') return null;

    // paréntesis = negativo, ej. (1,234.56)
    $isNeg = false;
    if ($s !== '' && $s[0] === '(' && substr($s, -1) === ')') {
        $isNeg = true;
        $s = substr($s, 1, -1);
    }

    // 1.234.567,89 -> 1234567.89
    if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $s)) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } else {
        // 1,234.56 -> 1234.56 ; 6331.61 se queda igual
        $s = str_replace(',', '', $s);
    }

    if ($s === '-' || $s === '') return null;
    if (!is_numeric($s)) return null;

    // limitar a 5 decimales (DECIMAL(20,5))
    if (strpos($s, '.') !== false) {
        [$ent, $dec] = explode('.', $s, 2);
        $dec = substr($dec, 0, 5);
        $s = ($dec === '') ? $ent : ($ent . '.' . $dec);
    }
    if ($isNeg && $s !== '0' && $s !== '0.0') $s = '-'.$s;
    return $s;
}
function toTextOrNull($raw) {
    // Mantener EXACTAMENTE el texto del Excel. Solo detectar vacío (tras trim) para NULL.
    if ($raw === null) return null;
    $orig = (string)$raw;
    return (trim($orig) === '') ? null : $orig; // no trim al valor guardado
}
function isDigitsId($v){
    if ($v === null) return false;
    $t = preg_replace('/\s+/', '', (string)$v);
    return preg_match('/^\d{6,15}$/', $t) === 1; // NIT/ID típico 6-15 dígitos
}
function isDigitsGeneric($v){
    if ($v === null) return false;
    $t = preg_replace('/\s+/', '', (string)$v);
    return preg_match('/^\d{1,15}$/', $t) === 1;
}
function isDigitsAccountCode($v){
    if ($v === null) return false;
    $t = preg_replace('/\s+/', '', (string)$v);
    return preg_match('/^\d{3,8}$/', $t) === 1; // p.ej., 1110, 720539, etc.
}

// ---------- Lector XLSX sin ZipArchive ----------
class XLSXLightReader {
    private $zipData;
    private $entries = [];
    private $strings = [];
    private $sheetTargets = [];

    public function __construct($xlsxPath) {
        $bin = @file_get_contents($xlsxPath);
        if ($bin === false || strlen($bin) < 4 || substr($bin,0,2)!=='PK') {
            throw new Exception('Archivo no es ZIP/XLSX válido.');
        }
        $this->zipData = $bin;
        $this->indexEntries();
        $this->loadSharedStrings();
        $this->discoverWorksheets();
    }
    private function indexEntries(){
        $d=$this->zipData; $len=strlen($d); $p=0;
        while (($p=strpos($d,"PK\x03\x04",$p))!==false){
            if ($p+30>$len) break;
            $hdr = substr($d,$p,30);
            $h = unpack('Vsignature/vversion/vflag/vmethod/vmtime/vmdate/Vcrc/Vcompsize/Vuncompsize/vnamelen/vextralen',$hdr);
            $nameLen  = (int)$h['namelen'];
            $extraLen = (int)$h['extralen'];
            $flag     = (int)$h['flag'];
            $method   = (int)$h['method'];
            $compSize = (int)$h['compsize'];
            if ($p+30+$nameLen>$len){ $p++; continue; }
            $name = substr($d,$p+30,$nameLen);
            $dataPos = $p + 30 + $nameLen + $extraLen;
            $hasDD   = (($flag & 0x08)!==0);
            $realComp = $compSize;
            if ($hasDD){
                $ddPos = $this->findDataDescriptor($d, $dataPos);
                if ($ddPos===false){ $p++; continue; }
                $realComp = $ddPos - $dataPos;
                $p = $ddPos + 16;
            } else {
                $p = $dataPos + $realComp;
            }
            $this->entries[$name] = ['pos'=>$dataPos,'comp'=>$realComp,'method'=>$method,'dd'=>$hasDD];
        }
        if (empty($this->entries)) throw new Exception('No se pudieron indexar entradas del XLSX.');
    }
    private function findDataDescriptor($zip,$start){
        $len=strlen($zip);
        for ($i=$start; $i<$len-4; $i++){ if (substr($zip,$i,4)==="PK\x07\x08") return $i; }
        return false;
    }
    private function readEntry($name){
        if (!isset($this->entries[$name])) return null;
        $m=$this->entries[$name];
        $slice = substr($this->zipData, $m['pos'], $m['comp']);
        if ($m['method']==0) return $slice;
        if ($m['method']==8){ $out=@gzinflate($slice); return ($out===false)?null:$out; }
        return null;
    }
    private function normalizeTarget($t){
        $t = ltrim($t,'/');
        while (strpos($t,'../')===0) $t = substr($t,3);
        if (strpos($t,'xl/')!==0) $t = 'xl/'.$t;
        return $t;
    }
    private function loadSharedStrings(){
        $xml = $this->readEntry('xl/sharedStrings.xml'); if (!$xml) return;
        if (preg_match_all('/<si[^>]*>(.*?)<\/si>/s',$xml,$blocks)){
            foreach ($blocks[1] as $blk){
                $txt='';
                if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s',$blk,$ts)){
                    foreach ($ts[1] as $t){ $txt .= html_entity_decode($t, ENT_XML1|ENT_QUOTES, 'UTF-8'); }
                }
                $this->strings[]=$txt;
            }
        }
    }
    private function discoverWorksheets(){
        $wb   = $this->readEntry('xl/workbook.xml');
        $rels = $this->readEntry('xl/_rels/workbook.xml.rels');
        $targets=[];
        if ($wb && $rels){
            $rids=[];
            if (preg_match_all('/<sheet[^>]+r:id="([^"]+)"/s',$wb,$m)){ $rids=$m[1]; }
            $map=[];
            if (preg_match_all('/<Relationship[^>]+Id="([^"]+)"[^>]+Target="([^"]+)"/s',$rels,$mr,PREG_SET_ORDER)){
                foreach($mr as $r){ $map[$r[1]]=$this->normalizeTarget($r[2]); }
            }
            foreach ($rids as $rid){ if (isset($map[$rid])) $targets[]=$map[$rid]; }
        }
        if (empty($targets)){
            foreach ($this->entries as $name=>$meta){ if (preg_match('#^xl/worksheets/[^/]+\.xml$#',$name)) $targets[]=$name; }
            sort($targets);
        }
        if (empty($targets)) throw new Exception('No se encontró ninguna hoja.');
        $this->sheetTargets = $targets;
    }
    public function getFirstSheetRows(){
        $xml = $this->readEntry($this->sheetTargets[0]);
        if (!$xml) throw new Exception('No se pudo abrir la hoja 1.');
        return $this->parseSheet($xml);
    }
    private function colToIndex($letters){
        $letters=strtoupper($letters); $n=0;
        for($i=0;$i<strlen($letters);$i++){ $n=$n*26 + (ord($letters[$i])-64); }
        return $n-1;
    }
    private function parseSheet($xml){
        $grid=[]; $maxR=0; $maxC=0;
        if (preg_match_all('/<c[^>]*r="([A-Z]+)(\d+)"[^>]*>(.*?)<\/c>/s',$xml,$cells,PREG_SET_ORDER)){
            foreach($cells as $c){
                $colL=$c[1]; $row1=(int)$c[2]; $inner=$c[3];
                $row=$row1-1; $col=$this->colToIndex($colL);
                $val='';
                if (preg_match('/<is[^>]*>.*?<t[^>]*>(.*?)<\/t>.*?<\/is>/s',$inner,$is)){
                    $val = html_entity_decode($is[1], ENT_XML1|ENT_QUOTES, 'UTF-8');
                } elseif (preg_match('/t="s".*?<v[^>]*>(.*?)<\/v>/s',$c[0],$v)){
                    $ix=(int)$v[1]; $val = $this->strings[$ix] ?? (string)$v[1];
                } elseif (preg_match('/<t[^>]*>(.*?)<\/t>/s',$inner,$t)){
                    $val = html_entity_decode($t[1], ENT_XML1|ENT_QUOTES, 'UTF-8');
                } elseif (preg_match('/<v[^>]*>(.*?)<\/v>/s',$inner,$v2)){
                    $val = (string)$v2[1];
                } else { $val=''; }
                $grid["$row-$col"]=$val;
                if ($row>$maxR) $maxR=$row; if ($col>$maxC) $maxC=$col;
            }
        }
        if (preg_match_all('/<c[^>]*r="([A-Z]+)(\d+)"[^>]*\/>/s',$xml,$empties,PREG_SET_ORDER)){
            foreach($empties as $c){
                $colL=$c[1]; $row1=(int)$c[2];
                $row=$row1-1; $col=$this->colToIndex($colL);
                if (!isset($grid["$row-$col"])) { $grid["$row-$col"]=''; if ($row>$maxR) $maxR=$row; if ($col>$maxC) $maxC=$col; }
            }
        }
        $rows=[];
        for ($r=0;$r<=$maxR;$r++){
            $line=[];
            for ($c=0;$c<=$maxC;$c++){
                $k="$r-$c"; $line[] = array_key_exists($k,$grid)?$grid[$k]:'';
            }
            $rows[]=$line;
        }
        return $rows;
    }
}

// ---------- Proceso principal ----------
$message=''; $messageType='';

if ($_SERVER['REQUEST_METHOD']==='POST'){
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')){
        $message='Token de seguridad inválido.'; $messageType='danger';
    } elseif (!isset($_FILES['xlsx_file']) || $_FILES['xlsx_file']['error']!==UPLOAD_ERR_OK){
        $message='Error al subir el archivo.'; $messageType='danger';
    } else {
        $file=$_FILES['xlsx_file'];
        if ($file['size']>MAX_FILE_SIZE){
            $message='El archivo es demasiado grande. Máximo 40MB.'; $messageType='danger';
        } else {
            $ext=strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS)){
                $message='Solo se permiten archivos XLSX.'; $messageType='danger';
            } else {
                try{
                    if (!$conn2) throw new Exception('Error de conexión a la base de datos');
                    mysqli_set_charset($conn2,'utf8mb4');

                    $reader = new XLSXLightReader($file['tmp_name']);
                    $rows   = $reader->getFirstSheetRows();
                    if (empty($rows)) throw new Exception('El XLSX está vacío.');

                    // === Encabezados: SIEMPRE fila 1 (índice 0) ===
                    $hdrIdx = 0;
                    $hdrRow = $rows[0];

                    // Mapa esperado (normalizado -> nombre BD)
                    $expected = [
                        'empresa'                => 'Empresa',
                        'tipo_documento'         => 'Tipo_Documento',
                        'nombre_documento'       => 'Nombre_Documento',
                        'prefijo'                => 'prefijo',
                        'numero_documento'       => 'Numero_Documento',
                        'fecha'                  => 'Fecha',
                        'tercero'                => 'Tercero',
                        'clasificacion'          => 'Clasificacion',
                        'codigo_cuenta'          => 'Codigo_Cuenta',
                        'nombre_cuenta'          => 'Nombre_Cuenta',
                        'debito'                 => 'Debito',
                        'credito'                => 'Credito',
                        'concepto_detalle'       => 'Concepto_Detalle',
                        'centro_de_costos'       => 'Centro_de_Costos',
                        'identificacion_tercero' => 'Identificacion_Tercero'
                    ];

                    // Índice por header normalizado EXACTO de la fila 1
                    $indexByNorm=[];
                    foreach ($hdrRow as $i=>$label){
                        $nk = normKey($label);
                        if ($nk!=='') $indexByNorm[$nk]=$i;
                    }

                    // Resolver índices para las 15 columnas requeridas
                    $colIndex=[];
                    foreach ($expected as $nk=>$dbField){
                        if (!array_key_exists($nk,$indexByNorm)){
                            throw new Exception("No se encontró el encabezado esperado: '$dbField' (clave '$nk') en la fila 1.");
                        }
                        $colIndex[$dbField] = $indexByNorm[$nk];
                    }

                    // Preparar INSERT
                    $place=implode(',', array_fill(0,count(DB_FIELDS),'?'));
                    $sql="INSERT INTO `".DB_TABLE."` (".implode(',',DB_FIELDS).") VALUES ($place)";
                    $stmt=$conn2->prepare($sql);
                    if (!$stmt) throw new Exception('Error preparando la consulta: '.$conn2->error);
                    $types=str_repeat('s', count(DB_FIELDS)); // MySQL convierte tipos

                    $inserted=0; $errors=[];
                    for ($r=$hdrIdx+1; $r<count($rows); $r++){
                        $row=$rows[$r];

                        // Armar registro por nombre (posiciones fijas desde encabezados)
                        $rec=[]; $allNull=true;
                        foreach (DB_FIELDS as $f){
                            $ix = $colIndex[$f];
                            $val = isset($row[$ix]) ? $row[$ix] : '';
                            if ($val !== '' && $val !== null) $allNull=false;
                            $rec[$f] = $val;
                        }
                        if ($allNull) continue;

                        // Reglas: Fecha / Debito / Credito
                        $rec['Fecha']   = toDateYmdOrNull($rec['Fecha']);
                        $rec['Debito']  = toDecimalOrNull($rec['Debito']);
                        $rec['Credito'] = toDecimalOrNull($rec['Credito']);

                        // Resto: texto idéntico o NULL
                        $textCols = [
                            'Empresa','Tipo_Documento','Nombre_Documento','prefijo',
                            'Numero_Documento','Tercero','Clasificacion','Codigo_Cuenta',
                            'Nombre_Cuenta','Concepto_Detalle','Centro_de_Costos','Identificacion_Tercero'
                        ];
                        foreach ($textCols as $tc){ $rec[$tc] = toTextOrNull($rec[$tc]); }

                        // --- FIX 1: Centro_de_Costos con dígitos y Ident vacío -> mover a Ident ---
                        if (isDigitsId($rec['Centro_de_Costos']) &&
                            ($rec['Identificacion_Tercero'] === null || $rec['Identificacion_Tercero'] === '')) {
                            $rec['Identificacion_Tercero'] = $rec['Centro_de_Costos'];
                            $rec['Centro_de_Costos'] = null;
                        }

                        // --- FIX 2: prefijo con dígitos y Numero_Documento vacío -> mover a Numero ---
                        if (isDigitsGeneric($rec['prefijo']) &&
                            ($rec['Numero_Documento'] === null || $rec['Numero_Documento'] === '')) {
                            $rec['Numero_Documento'] = $rec['prefijo'];
                            $rec['prefijo'] = null;
                        }

                        // --- FIX 3: Clasificacion con dígitos (3–8) y Codigo_Cuenta vacío -> mover a Codigo_Cuenta ---
                        if (isDigitsAccountCode($rec['Clasificacion']) &&
                            ($rec['Codigo_Cuenta'] === null || $rec['Codigo_Cuenta'] === '')) {
                            $rec['Codigo_Cuenta'] = $rec['Clasificacion'];
                            $rec['Clasificacion'] = null;
                        }

                        // --- FIX 4: Centro & Ident vacíos y Concepto trae el ID -> mover a Ident y vaciar Concepto ---
                        $centroVacio = ($rec['Centro_de_Costos'] === null || $rec['Centro_de_Costos'] === '');
                        $identVacio  = ($rec['Identificacion_Tercero'] === null || $rec['Identificacion_Tercero'] === '');
                        if ($centroVacio && $identVacio && isDigitsId($rec['Concepto_Detalle'])) {
                            $rec['Identificacion_Tercero'] = $rec['Concepto_Detalle'];
                            $rec['Concepto_Detalle'] = null;
                        }
                        // ----------------------------------------------------------------------------------------------

                        // bind por referencia (NULL reales)
                        $vars=[]; foreach (DB_FIELDS as $f){ $vars[$f]=$rec[$f]; }
                        $params = [$types]; foreach (DB_FIELDS as $f){ $params[]=&$vars[$f]; }
                        call_user_func_array([$stmt,'bind_param'], $params);

                        if ($stmt->execute()) $inserted++;
                        else $errors[]="Fila ".($r+1).": ".$stmt->error;
                    }
                    $stmt->close();

                    if ($inserted>0){
                        $messageType='success';
                        $message="Proceso completado. Filas insertadas: $inserted.";
                        if (!empty($errors)){
                            $message.="<br><strong>Advertencias/Errores:</strong><br>".implode("<br>", array_slice($errors,0,20));
                            if (count($errors)>20) $message.="<br>... y ".(count($errors)-20)." más.";
                        }
                    } else {
                        $messageType='warning';
                        $message="No se insertó ninguna fila.";
                        if (!empty($errors)){
                            $message.="<br><strong>Errores:</strong><br>".implode("<br>", array_slice($errors,0,20));
                        }
                    }
                } catch(Exception $e){
                    $messageType='danger';
                    $message='Error: '.$e->getMessage();
                }
            }
        }
    }
}

// ---------- UI (estilo verde igual a costosusaUploader) ----------
include("includes/header.php");
$maxMb=(int)(MAX_FILE_SIZE/(1024*1024));
?>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h3 class="card-title mb-0"><i class="fas fa-file-excel me-2"></i> Subir Archivo XLSX - Costos (COL)
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <ul class="mb-0">
                            <li>Se toma <strong>la fila 1</strong> como encabezado (exactamente 15 columnas esperadas).
                            </li>
                            <li>Todo campo vacío se inserta como <strong>NULL</strong>.</li>
                            <li>Fecha: DD/MM/YYYY o serial → <code>YYYY-MM-DD</code>. Débito/Crédito → número (hasta 5
                                decimales, vacío = NULL).</li>
                            <li>Tamaño máximo: <?php echo $maxMb; ?> MB.</li>
                        </ul>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label for="xlsx_file" class="form-label">
                                <i class="fas fa-file-excel me-2"></i>Seleccionar archivo XLSX (Costos COL):
                            </label>
                            <input type="file" name="xlsx_file" id="xlsx_file" class="form-control"
                                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                required>
                            <div class="form-text">La primera fila del archivo debe contener estos encabezados:
                                <?php echo implode(', ', DB_FIELDS); ?></div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                <i class="fas fa-cloud-upload-alt me-2"></i>Procesar XLSX de Costos
                            </button>
                            <button type="button" class="btn btn-outline-success btn-lg"
                                onclick="window.location.href='updater.php'">
                                <i class="fa-solid fa-circle-arrow-left me-2"></i>Regresar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('uploadForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando XLSX...';
});
document.getElementById('xlsx_file')?.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    if (file.size > <?php echo MAX_FILE_SIZE; ?>) {
        alert('Máximo <?php echo $maxMb; ?>MB.');
        this.value = '';
        return;
    }
    if (!file.name.toLowerCase().endsWith('.xlsx')) {
        alert('Solo .xlsx');
        this.value = '';
        return;
    }
});
</script>
<?php include("includes/footer.php"); ?>