<?php
require 'conexion.php';

/* ==========================================================================
 * Funciones auxiliares
 * ========================================================================== */

/**
 * Convierte un número de minutos a un formato de "Xh Ymin" o "Ymin".
 */
function formatMinutesToHoursAndMinutes(int $totalMinutes): string
{
    if ($totalMinutes < 60) {
        return $totalMinutes . 'min';
    }
    $hours = intdiv($totalMinutes, 60);
    $minutes = $totalMinutes % 60;
    return $hours . 'h ' . $minutes . 'min';
}

/** Formatea un porcentaje sin decimales innecesarios (80 => "80", 80.5 => "80.5"). */
function formatPct(float $value): string
{
    return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . '%';
}

/** Devuelve un texto tipo "hace 3 h" a partir de un timestamp. */
function formatRelativeTime(int $timestamp): string
{
    $diff = max(0, time() - $timestamp);
    if ($diff < 60) return 'hace instantes';
    if ($diff < 3600) return 'hace ' . intdiv($diff, 60) . ' min';
    if ($diff < 86400) return 'hace ' . intdiv($diff, 3600) . ' h';
    $dias = intdiv($diff, 86400);
    return 'hace ' . $dias . ($dias === 1 ? ' día' : ' días');
}

/** Convierte un valor de BD a float, o null si viene vacío. */
function toFloatOrNull($value): ?float
{
    return ($value === null || $value === '') ? null : (float)$value;
}

/** Mediana de un arreglo numérico. */
function mediana(array $values): float
{
    if (empty($values)) return 0;
    sort($values);
    $n = count($values);
    $mid = intdiv($n, 2);
    return $n % 2 ? (float)$values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
}

/**
 * Renderiza una tarjeta de estadística con el mismo marcado que el diseño original.
 */
function statCard(string $label, string $value, string $valueClass, string $iconPath, string $sub = ''): void
{
    echo '<div class="rounded-xl border border-gray-100 bg-gray-50/40 p-4 transition-all hover:shadow-md hover:bg-white">';
    echo '<p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">' . htmlspecialchars($label) . '</p>';
    echo '<div class="flex items-baseline justify-between">';
    echo '<span class="text-2xl font-bold ' . $valueClass . '">' . htmlspecialchars($value) . '</span>';
    echo '<svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" d="' . $iconPath . '" /></svg>';
    echo '</div>';
    if ($sub !== '') {
        echo '<p class="mt-1 text-xs text-gray-400">' . htmlspecialchars($sub) . '</p>';
    }
    echo '</div>';
}

// Paths de íconos reutilizados
const ICON_DOC      = 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
const ICON_CALC     = 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 11h.01M9 11h.01M12 7h.01M15 11h.01M4 19V5a2 2 0 012-2h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2z';
const ICON_CLOCK    = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
const ICON_DOWN     = 'M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6';
const ICON_UP       = 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6';
const ICON_BOLT     = 'M13 10V3L4 14h7v7l9-11h-7z';
const ICON_CALENDAR = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z';
const ICON_CHART    = 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z';
const ICON_ALERT    = 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z';
const ICON_CHECK    = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
const ICON_REFRESH  = 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15';

/* ==========================================================================
 * Parámetros y variables
 * ========================================================================== */

$nombres_dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$umbral_bajo = 20;
$umbral_alto = 80;
$objetivo_carga = 80;  // Porcentaje objetivo al terminar la carga
$tolerancia = 2;       // Margen (en puntos) para considerar que se alcanzó el objetivo

// Filtro de periodo (0 = todo el historial)
$periodos_validos = [7 => 'Últimos 7 días', 30 => 'Últimos 30 días', 90 => 'Últimos 90 días', 0 => 'Todo'];
$periodo = isset($_GET['dias']) ? (int)$_GET['dias'] : 0;
if (!array_key_exists($periodo, $periodos_validos)) {
    $periodo = 0;
}

$registros = [];
$error_carga = false;

// Métricas generales
$total_registros = 0;
$promedio_porcentaje_actual = 0;
$mediana_porcentaje = 0;
$total_minutos_carga = 0;
$min_porcentaje = 0;
$max_porcentaje = 0;

// Métricas de duración
$promedio_minutos_por_carga = 0;
$max_minutos = 0;
$min_minutos = 0;

// Métricas de hábitos
$cargas_bajas = 0;
$cargas_altas = 0;
$dias_con_registro = 0;
$cargas_por_dia = 0;
$ultimo_registro_ts = null;
$dia_semana_top = 'N/A';
$hora_top = 'N/A';

// Métricas de resultado y cumplimiento del objetivo
$registros_con_final = 0;
$promedio_final = 0;
$ganancia_promedio = 0;
$faltante_promedio = 0;
$min_por_punto = 0;
$desvio_promedio = 0;
$cargas_en_objetivo = 0;
$cargas_sobre_objetivo = 0;
$cargas_bajo_objetivo = 0;
$finales_chart = [];
$scatter_chart = [];

// Datos para gráficos
$fechas_chart = [];
$porcentajes_chart = [];
$promedio_minutos_por_dia = [];
$dias_chart = [];
$minutos_promedio_chart = [];
$cargas_dia_chart = [];
$distribucion_labels = ['0-19%', '20-39%', '40-59%', '60-79%', '80-100%'];
$distribucion_chart = [0, 0, 0, 0, 0];
$semana_chart = array_fill(0, 7, 0);

try {
    // Consulta preparada; el filtro de fecha se resuelve en PHP para ser portable entre motores
    if ($periodo > 0) {
        $desde = date('Y-m-d H:i:s', strtotime("-{$periodo} days"));
        $stmt = $pdo->prepare(
            "SELECT fecha_registro, porcentaje_actual, porcentaje_faltante, porcentaje_final_real, minutos_carga
             FROM registros_bateria
             WHERE fecha_registro >= :desde
             ORDER BY fecha_registro ASC"
        );
        $stmt->execute([':desde' => $desde]);
    } else {
        $stmt = $pdo->query(
            "SELECT fecha_registro, porcentaje_actual, porcentaje_faltante, porcentaje_final_real, minutos_carga
             FROM registros_bateria
             ORDER BY fecha_registro ASC"
        );
    }
    $registros = $stmt->fetchAll();
    $total_registros = count($registros);

    if ($total_registros > 0) {
        $porcentajes = [];
        $minutos = [];
        $por_dia = [];
        $horas = array_fill(0, 24, 0);
        $faltantes = [];
        $finales = [];
        $ganancias = [];
        $desvios = [];
        $suma_min_ganancia = 0;
        $suma_ganancia_pos = 0;

        foreach ($registros as $registro) {
            $pct = (float)$registro['porcentaje_actual'];
            $min = (int)$registro['minutos_carga'];
            $final = toFloatOrNull($registro['porcentaje_final_real'] ?? null);
            $faltante = toFloatOrNull($registro['porcentaje_faltante'] ?? null);
            $ts = strtotime($registro['fecha_registro']);
            $fecha = date('Y-m-d', $ts);

            $porcentajes[] = $pct;
            $minutos[] = $min;

            // Gráfico de evolución
            $fechas_chart[] = date('d/m H:i', $ts);
            $porcentajes_chart[] = $pct;

            // Agrupación por día
            if (!isset($por_dia[$fecha])) {
                $por_dia[$fecha] = ['n' => 0, 'suma_min' => 0, 'suma_gan' => 0, 'n_gan' => 0];
            }
            $por_dia[$fecha]['n']++;
            $por_dia[$fecha]['suma_min'] += $min;

            // Resultado de la carga
            $finales_chart[] = $final;
            if ($faltante !== null) {
                $faltantes[] = $faltante;
            }
            if ($final !== null) {
                $ganancia = $final - $pct;
                $finales[] = $final;
                $ganancias[] = $ganancia;
                $desvios[] = $final - $objetivo_carga;

                if ($ganancia > 0) {
                    $suma_min_ganancia += $min;
                    $suma_ganancia_pos += $ganancia;
                }
                $por_dia[$fecha]['suma_gan'] += $ganancia;
                $por_dia[$fecha]['n_gan']++;
                $scatter_chart[] = ['x' => round($ganancia, 1), 'y' => $min];

                if ($final > $objetivo_carga + $tolerancia) {
                    $cargas_sobre_objetivo++;
                } elseif ($final < $objetivo_carga - $tolerancia) {
                    $cargas_bajo_objetivo++;
                } else {
                    $cargas_en_objetivo++;
                }
            }

            // Hábitos
            if ($pct < $umbral_bajo) $cargas_bajas++;
            if ($pct >= $umbral_alto) $cargas_altas++;

            $bucket = min(4, (int)floor($pct / 20));
            $distribucion_chart[$bucket]++;

            $semana_chart[(int)date('w', $ts)]++;
            $horas[(int)date('G', $ts)]++;

            $ultimo_registro_ts = $ts; // los registros vienen ordenados ASC
        }

        $promedio_porcentaje_actual = round(array_sum($porcentajes) / $total_registros, 1);
        $mediana_porcentaje = mediana($porcentajes);
        $min_porcentaje = min($porcentajes);
        $max_porcentaje = max($porcentajes);

        $total_minutos_carga = array_sum($minutos);
        $promedio_minutos_por_carga = (int)round($total_minutos_carga / $total_registros);
        $max_minutos = max($minutos);
        $min_minutos = min($minutos);

        // Resultado de la carga y cumplimiento del objetivo
        $registros_con_final = count($finales);
        if ($registros_con_final > 0) {
            $promedio_final = round(array_sum($finales) / $registros_con_final, 1);
            $ganancia_promedio = round(array_sum($ganancias) / $registros_con_final, 1);
            $desvio_promedio = round(array_sum($desvios) / $registros_con_final, 1);
            $min_por_punto = $suma_ganancia_pos > 0 ? round($suma_min_ganancia / $suma_ganancia_pos, 1) : 0;
        }
        if (!empty($faltantes)) {
            $faltante_promedio = round(array_sum($faltantes) / count($faltantes), 1);
        }

        // Resumen diario
        foreach ($por_dia as $fecha => $d) {
            $promedio = round($d['suma_min'] / $d['n'], 2);
            $promedio_minutos_por_dia[] = [
                'fecha' => $fecha,
                'promedio_carga' => $promedio,
                'cargas' => $d['n'],
                'total_carga' => $d['suma_min'],
                'ganancia_prom' => $d['n_gan'] > 0 ? round($d['suma_gan'] / $d['n_gan'], 1) : null,
            ];
            $dias_chart[] = date('d/m/Y', strtotime($fecha));
            $minutos_promedio_chart[] = $promedio;
            $cargas_dia_chart[] = $d['n'];
        }

        $dias_con_registro = count($por_dia);
        $cargas_por_dia = round($total_registros / $dias_con_registro, 1);

        // Día de la semana y hora más frecuentes
        $max_semana = max($semana_chart);
        $dia_semana_top = $nombres_dias[array_search($max_semana, $semana_chart)];
        $max_hora = max($horas);
        $h = array_search($max_hora, $horas);
        $hora_top = sprintf('%02d:00 - %02d:59', $h, $h);
    }
} catch (PDOException $e) {
    // Se registra el detalle y se muestra un mensaje genérico al usuario
    error_log('Dashboard batería: ' . $e->getMessage());
    $error_carga = true;
}

$pct_bajas = $total_registros > 0 ? round($cargas_bajas / $total_registros * 100) : 0;
$pct_altas = $total_registros > 0 ? round($cargas_altas / $total_registros * 100) : 0;

// Indicador de hábito de carga (heurística basada en el % de cargas con batería baja)
if ($pct_bajas <= 15) {
    $habito = ['bg-green-50 border-green-200 text-green-800', ICON_CHECK, 'Buen hábito de carga',
        'Solo el ' . $pct_bajas . '% de tus registros ocurre con menos de ' . $umbral_bajo . '% de batería.'];
} elseif ($pct_bajas <= 40) {
    $habito = ['bg-yellow-50 border-yellow-200 text-yellow-800', ICON_ALERT, 'Hábito de carga mejorable',
        'El ' . $pct_bajas . '% de tus registros ocurre con menos de ' . $umbral_bajo . '% de batería. Intenta cargar antes de llegar a ese nivel.'];
} else {
    $habito = ['bg-red-50 border-red-200 text-red-800', ICON_ALERT, 'Descargas profundas frecuentes',
        'El ' . $pct_bajas . '% de tus registros ocurre con menos de ' . $umbral_bajo . '% de batería, lo que puede acelerar el desgaste.'];
}

// Indicador de cumplimiento del objetivo de carga
$pct_sobre = $registros_con_final > 0 ? round($cargas_sobre_objetivo / $registros_con_final * 100) : 0;
$pct_en_obj = $registros_con_final > 0 ? round($cargas_en_objetivo / $registros_con_final * 100) : 0;
$pct_cortas = $registros_con_final > 0 ? round($cargas_bajo_objetivo / $registros_con_final * 100) : 0;
$limite_sup = $objetivo_carga + $tolerancia;

if ($pct_sobre <= 10) {
    $habito_obj = ['bg-green-50 border-green-200 text-green-800', ICON_CHECK, 'Respetas el límite de ' . $objetivo_carga . '%',
        'Solo el ' . $pct_sobre . '% de tus cargas terminó sobre ' . $limite_sup . '%. Promedio final: ' . formatPct((float)$promedio_final) . '.'];
} elseif ($pct_sobre <= 30) {
    $habito_obj = ['bg-yellow-50 border-yellow-200 text-yellow-800', ICON_ALERT, 'A veces superas el límite de ' . $objetivo_carga . '%',
        'El ' . $pct_sobre . '% de tus cargas terminó sobre ' . $limite_sup . '%. Desconectar a tiempo ayuda a conservar la batería.'];
} else {
    $habito_obj = ['bg-red-50 border-red-200 text-red-800', ICON_ALERT, 'Superas el límite de ' . $objetivo_carga . '% con frecuencia',
        'El ' . $pct_sobre . '% de tus cargas terminó sobre ' . $limite_sup . '%. Promedio final: ' . formatPct((float)$promedio_final) . '.'];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestor de Batería</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container-main {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 30px;
            margin-bottom: 25px;
        }

        .header-title {
            color: #333;
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
        }

        .header-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1rem;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #f0f4ff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .stat-label {
            color: #555;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .stat-value {
            color: #667eea;
            font-size: 1.8rem;
            font-weight: bold;
        }

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        .nav-link {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }

        .nav-link:hover {
            background: #764ba2;
        }

        .no-records {
            text-align: center;
            color: #666;
            padding: 30px 10px;
        }
    </style>
</head>

<body>
    <div class="container-main">
        <!-- Cabecera del Dashboard -->
        <header class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-gray-100 pb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-2xl">📊</span>

                    <h1 class="bg-gradient-to-r from-white via-indigo-100 to-indigo-200 bg-clip-text text-2xl font-extrabold tracking-tight text-transparent sm:text-3xl">
                        Dashboard de Batería
                    </h1>
                </div>
                <p class="text-sm font-medium text-black">
                    Análisis y tendencias de carga de tu Chromebook
                </p>
            </div>

            <!-- Enlace de regreso estilizado como botón secundario -->
            <div>
                <a href="index.php" class="group inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-all duration-200 hover:bg-gray-50 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <svg class="h-4 w-4 text-indigo-500 transition-transform duration-200 group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Volver al Registro de Carga</span>
                </a>
            </div>
        </header>

        <?php if ($error_carga): ?>
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">
                No se pudieron cargar los datos del dashboard. Inténtalo de nuevo más tarde.
            </div>
        <?php endif; ?>

        <!-- Filtro de periodo -->
        <div class="mb-6 flex flex-wrap items-center gap-2">
            <?php foreach ($periodos_validos as $dias => $etiqueta): ?>
                <?php $activo = ($dias === $periodo); ?>
                <a href="?dias=<?php echo $dias; ?>"
                    class="rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition-all duration-200 <?php echo $activo ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                    <?php echo $etiqueta; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Sección de Estadísticas Clave -->
        <div class="mb-8 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo ICON_UP; ?>" />
                </svg>
                Estadísticas Clave
                <span class="text-sm font-medium text-gray-400">· <?php echo $periodos_validos[$periodo]; ?></span>
            </h2>

            <!-- Nivel de batería -->
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-indigo-500">Nivel de batería</p>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 mb-8">
                <?php
                statCard('Registros Totales', (string)$total_registros, 'text-gray-900', ICON_DOC);
                statCard('% Actual Promedio', $total_registros ? formatPct($promedio_porcentaje_actual) : 'N/A', 'text-indigo-600', ICON_CALC,
                    $total_registros ? 'Mediana: ' . formatPct($mediana_porcentaje) : '');
                statCard('Porcentaje Mínimo', $total_registros ? formatPct($min_porcentaje) : 'N/A', 'text-red-600', ICON_DOWN);
                statCard('Porcentaje Máximo', $total_registros ? formatPct($max_porcentaje) : 'N/A', 'text-green-600', ICON_UP);
                statCard('Último Registro', $ultimo_registro_ts ? formatRelativeTime($ultimo_registro_ts) : 'N/A', 'text-gray-900 text-xl leading-8', ICON_REFRESH,
                    $ultimo_registro_ts ? date('d/m/Y H:i', $ultimo_registro_ts) : '');
                ?>
            </div>

            <!-- Duración de las cargas -->
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-indigo-500">Duración de las cargas</p>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 mb-8">
                <?php
                statCard('Total Minutos Carga', formatMinutesToHoursAndMinutes($total_minutos_carga), 'text-gray-900 text-xl leading-7', ICON_CLOCK);
                statCard('Promedio por Carga', $total_registros ? formatMinutesToHoursAndMinutes($promedio_minutos_por_carga) : 'N/A', 'text-purple-600 text-xl leading-7', ICON_BOLT);
                statCard('Carga Más Larga', $total_registros ? formatMinutesToHoursAndMinutes($max_minutos) : 'N/A', 'text-gray-900 text-xl leading-7', ICON_UP);
                statCard('Carga Más Corta', $total_registros ? formatMinutesToHoursAndMinutes($min_minutos) : 'N/A', 'text-gray-900 text-xl leading-7', ICON_DOWN);
                ?>
            </div>

            <!-- Resultado de la carga -->
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-indigo-500">Resultado de la carga</p>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 mb-8">
                <?php
                statCard('% Final Promedio', $registros_con_final ? formatPct((float)$promedio_final) : 'N/A', 'text-green-600', ICON_CHECK,
                    $registros_con_final ? 'Objetivo: ' . $objetivo_carga . '%' : 'Sin datos de % final');
                statCard('Ganancia Promedio', $registros_con_final ? '+' . formatPct((float)$ganancia_promedio) : 'N/A', 'text-indigo-600', ICON_UP, 'Por carga (final − inicio)');
                statCard('Faltante Promedio', $faltante_promedio > 0 ? formatPct((float)$faltante_promedio) : 'N/A', 'text-gray-900', ICON_DOWN, 'Al iniciar, para llegar a ' . $objetivo_carga . '%');
                statCard('Min por 1% Cargado', $min_por_punto > 0 ? $min_por_punto . ' min' : 'N/A', 'text-purple-600', ICON_BOLT, 'Velocidad media de carga');
                ?>
            </div>

            <!-- Cumplimiento del objetivo -->
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-indigo-500">Cumplimiento del objetivo (<?php echo $objetivo_carga; ?>% ±<?php echo $tolerancia; ?>)</p>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 mb-8">
                <?php
                statCard('En el Objetivo', $registros_con_final ? $cargas_en_objetivo . ' (' . $pct_en_obj . '%)' : 'N/A', 'text-green-600', ICON_CHECK);
                statCard('Sobrepasaron', $registros_con_final ? $cargas_sobre_objetivo . ' (' . $pct_sobre . '%)' : 'N/A', 'text-red-600', ICON_ALERT, 'Terminaron sobre ' . $limite_sup . '%');
                statCard('Se Quedaron Cortas', $registros_con_final ? $cargas_bajo_objetivo . ' (' . $pct_cortas . '%)' : 'N/A', 'text-yellow-600', ICON_DOWN, 'Terminaron bajo ' . ($objetivo_carga - $tolerancia) . '%');
                statCard('Desvío Promedio', $registros_con_final ? ($desvio_promedio > 0 ? '+' : '') . $desvio_promedio . ' pts' : 'N/A', 'text-gray-900', ICON_CALC, 'Final vs ' . $objetivo_carga . '%');
                ?>
            </div>

            <!-- Hábitos de uso -->
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-indigo-500">Hábitos de carga</p>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <?php
                statCard('Días con Registro', (string)$dias_con_registro, 'text-gray-900', ICON_CALENDAR);
                statCard('Cargas por Día', $total_registros ? (string)$cargas_por_dia : 'N/A', 'text-indigo-600', ICON_CHART, 'Promedio en días activos');
                statCard('Día Más Activo', $dia_semana_top, 'text-gray-900 text-xl leading-8', ICON_CALENDAR);
                statCard('Hora Frecuente', $hora_top, 'text-gray-900 text-base leading-8', ICON_CLOCK);
                statCard('Batería Baja (<' . $umbral_bajo . '%)', $cargas_bajas . ' (' . $pct_bajas . '%)', 'text-red-600', ICON_ALERT,
                    'Iniciadas con ≥' . $umbral_alto . '%: ' . $cargas_altas . ' (' . $pct_altas . '%)');
                ?>
            </div>

            <?php if ($total_registros > 0): ?>
                <!-- Indicador de hábito -->
                <div class="mt-6 flex items-start gap-3 rounded-xl border p-4 <?php echo $habito[0]; ?>">
                    <svg class="h-5 w-5 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $habito[1]; ?>" />
                    </svg>
                    <div>
                        <p class="text-sm font-bold"><?php echo $habito[2]; ?></p>
                        <p class="text-sm"><?php echo htmlspecialchars($habito[3]); ?></p>
                    </div>
                </div>

                <?php if ($registros_con_final > 0): ?>
                    <!-- Indicador de objetivo -->
                    <div class="mt-4 flex items-start gap-3 rounded-xl border p-4 <?php echo $habito_obj[0]; ?>">
                        <svg class="h-5 w-5 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $habito_obj[1]; ?>" />
                        </svg>
                        <div>
                            <p class="text-sm font-bold"><?php echo $habito_obj[2]; ?></p>
                            <p class="text-sm"><?php echo htmlspecialchars($habito_obj[3]); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_registros > 0): ?>
            <div class="card">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Evolución del Porcentaje de Batería</h2>
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="batteryChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Distribución y Frecuencia</h2>
                <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-3 text-center">Registros por rango de batería</h3>
                        <div class="chart-container" style="position: relative; height:260px;">
                            <canvas id="distributionChart"></canvas>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-3 text-center">Registros por día de la semana</h3>
                        <div class="chart-container" style="position: relative; height:260px;">
                            <canvas id="weekdayChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($registros_con_final > 0): ?>
                <div class="card">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Minutos de Carga vs. Porcentaje Ganado</h2>
                    <p class="text-sm text-gray-500 mb-6">Cada punto es una carga: cuánto subió la batería y cuánto tardó.</p>
                    <div class="chart-container" style="position: relative; height:300px;">
                        <canvas id="gainChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Promedio de Minutos de Carga por Día</h2>
            <?php if (!empty($promedio_minutos_por_dia)): ?>
                <div class="table-container mb-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm" style="max-height: 360px; overflow-y: auto;">
                    <table class="w-full border-collapse text-left text-sm text-gray-500">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-medium">Día</th>
                                <th scope="col" class="px-6 py-4 font-medium text-center">Cargas</th>
                                <th scope="col" class="px-6 py-4 font-medium text-right">Total</th>
                                <th scope="col" class="px-6 py-4 font-medium text-right">Ganancia Prom.</th>
                                <th scope="col" class="px-6 py-4 font-medium text-right">Promedio de Carga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 border-t border-gray-100">
                            <?php foreach (array_reverse($promedio_minutos_por_dia) as $daily_avg): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo ICON_CALENDAR; ?>" />
                                            </svg>
                                            <?php echo date('d/m/Y', strtotime($daily_avg['fecha'])); ?>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-center font-semibold text-gray-700">
                                        <?php echo (int)$daily_avg['cargas']; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-semibold text-gray-700">
                                        <?php echo formatMinutesToHoursAndMinutes((int)$daily_avg['total_carga']); ?>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-semibold text-gray-700">
                                        <?php echo $daily_avg['ganancia_prom'] !== null ? '+' . formatPct((float)$daily_avg['ganancia_prom']) : '—'; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-semibold text-gray-700">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo ICON_CLOCK; ?>" />
                                            </svg>
                                            <?php echo formatMinutesToHoursAndMinutes((int)round($daily_avg['promedio_carga'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="dailyChargeChart"></canvas>
                </div>
            <?php else: ?>
                <div class="no-records">
                    No hay datos suficientes para calcular el promedio de carga por día<?php echo $periodo > 0 ? ' en este periodo' : ''; ?>.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($total_registros > 0): ?>
        <script>
            const formatMin = (value) => {
                const total = Math.round(value);
                const h = Math.floor(total / 60);
                const m = total % 60;
                return h > 0 ? h + 'h ' + m + 'min' : m + 'min';
            };

            // 1. Evolución de Batería (Línea) con umbral de batería baja
            new Chart(document.getElementById('batteryChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($fechas_chart); ?>,
                    datasets: [{
                            label: 'Porcentaje al Iniciar la Carga',
                            data: <?php echo json_encode($porcentajes_chart); ?>,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.2)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointRadius: <?php echo $total_registros > 60 ? 0 : 3; ?>
                        },
                        {
                            label: 'Porcentaje Final Real',
                            data: <?php echo json_encode($finales_chart); ?>,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.15)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            spanGaps: true,
                            pointRadius: <?php echo $total_registros > 60 ? 0 : 3; ?>
                        },
                        {
                            label: 'Objetivo (<?php echo $objetivo_carga; ?>%)',
                            data: <?php echo json_encode(array_fill(0, $total_registros, $objetivo_carga)); ?>,
                            borderColor: '#10b981',
                            borderWidth: 1.5,
                            borderDash: [6, 6],
                            pointRadius: 0,
                            fill: false
                        },
                        {
                            label: 'Umbral batería baja (<?php echo $umbral_bajo; ?>%)',
                            data: <?php echo json_encode(array_fill(0, $total_registros, $umbral_bajo)); ?>,
                            borderColor: '#ef4444',
                            borderWidth: 1.5,
                            borderDash: [6, 6],
                            pointRadius: 0,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Porcentaje de Batería (%)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Fecha y Hora'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => context.dataset.label + ': ' + context.raw + '%'
                            }
                        }
                    }
                }
            });

            // 2. Distribución por rango de batería (Dona)
            new Chart(document.getElementById('distributionChart'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($distribucion_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($distribucion_chart); ?>,
                        backgroundColor: ['#ef4444', '#f59e0b', '#a78bfa', '#667eea', '#10b981'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total ? Math.round(context.raw / total * 100) : 0;
                                    return context.label + ': ' + context.raw + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // 3. Registros por día de la semana (Barras)
            new Chart(document.getElementById('weekdayChart'), {
                type: 'bar',
                data: {
                    labels: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                    datasets: [{
                        label: 'Registros',
                        data: <?php echo json_encode(array_values($semana_chart)); ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // 3b. Minutos de carga vs. porcentaje ganado (Dispersión)
            const gainCtx = document.getElementById('gainChart');
            if (gainCtx) {
                new Chart(gainCtx, {
                    type: 'scatter',
                    data: {
                        datasets: [{
                            label: 'Cargas',
                            data: <?php echo json_encode($scatter_chart); ?>,
                            backgroundColor: 'rgba(118, 75, 162, 0.7)',
                            borderColor: 'rgba(118, 75, 162, 1)',
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Porcentaje ganado (puntos)'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Minutos de carga'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: (context) => '+' + context.raw.x + '% en ' + formatMin(context.raw.y)
                                }
                            }
                        }
                    }
                });
            }

            // 4. Promedio de minutos de carga por día (Barras) + cargas del día (Línea)
            const dailyChargeCtx = document.getElementById('dailyChargeChart');
            if (dailyChargeCtx) {
                new Chart(dailyChargeCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($dias_chart); ?>,
                        datasets: [{
                                type: 'bar',
                                label: 'Promedio de Minutos de Carga',
                                data: <?php echo json_encode($minutos_promedio_chart); ?>,
                                backgroundColor: 'rgba(118, 75, 162, 0.7)',
                                borderColor: 'rgba(118, 75, 162, 1)',
                                borderWidth: 1,
                                yAxisID: 'y'
                            },
                            {
                                type: 'line',
                                label: 'Cargas del día',
                                data: <?php echo json_encode($cargas_dia_chart); ?>,
                                borderColor: '#667eea',
                                backgroundColor: '#667eea',
                                borderWidth: 2,
                                tension: 0.3,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Minutos de Carga Promedio'
                                }
                            },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                grid: {
                                    drawOnChartArea: false
                                },
                                ticks: {
                                    precision: 0
                                },
                                title: {
                                    display: true,
                                    text: 'N.º de cargas'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Día'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        if (context.dataset.yAxisID === 'y1') {
                                            return 'Cargas: ' + context.raw;
                                        }
                                        return 'Promedio: ' + formatMin(context.raw);
                                    }
                                }
                            }
                        }
                    }
                });
            }
        </script>
    <?php endif; ?>
</body>

</html>