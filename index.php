<?php
require 'conexion.php';

/**
 * Convierte un número de minutos a un formato de "Xh Ymin" o "Ymin".
 */
function formatMinutesToHoursAndMinutes(int $totalMinutes): string
{
    if ($totalMinutes < 60) {
        return $totalMinutes . 'min';
    } else {
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        return $hours . 'h ' . $minutes . 'min';
    }
}

date_default_timezone_set('America/Santiago');
$fecha_actual_hora_actual = date('Y-m-d H:i');

// Variables para almacenar mensajes
$mensaje = '';
$tipo_mensaje = '';
$resultado_calculo = null;

// =========================================================================
// MEJORA: CALCULAR EL FACTOR DE ADAPTACIÓN INTELIGENTE (HISTORIAL)
// =========================================================================
$factor_correccion = 1.0; // 1.0 significa que carga exactamente al ritmo base de fábrica

try {
    // Obtenemos las últimas 5 cargas reales completadas con éxito
    $sql_historico = "SELECT porcentaje_actual, porcentaje_final_real, minutos_carga 
                      FROM registros_bateria 
                      WHERE porcentaje_final_real IS NOT NULL 
                      AND porcentaje_final_real > porcentaje_actual 
                      ORDER BY id DESC LIMIT 5";
    $stmt_hist = $pdo->query($sql_historico);
    $cargas_pasadas = $stmt_hist->fetchAll();

    if (count($cargas_pasadas) > 0) {
        $suma_factores = 0;
        $total_cargas_validas = 0;

        foreach ($cargas_pasadas as $cp) {
            $p_inicial = $cp['porcentaje_actual'];
            $p_final = $cp['porcentaje_final_real'];
            $minutos_reales = $cp['minutos_carga'];

            // Calcular cuánto debería haber tardado teóricamente según el modelo de tramos base (65% inflexión)
            $minutos_teoricos = 0;
            $p_temp = $p_inicial;
            $punto_inflexion = 65;
            $t_rapida_base = 1.1;
            $t_lenta_base = 1.5;

            if ($p_temp < $p_final) {
                if ($p_temp < $punto_inflexion) {
                    $puntos_t1 = min($punto_inflexion, $p_final) - $p_temp;
                    $minutos_teoricos += $puntos_t1 * $t_rapida_base;
                    $p_temp = $punto_inflexion;
                }
                if ($p_temp < $p_final) {
                    $puntos_t2 = $p_final - $p_temp;
                    $minutos_teoricos += $puntos_t2 * $t_lenta_base;
                }
            }

            if ($minutos_teoricos > 0) {
                // Relación real vs teórica (ej: si tomó 45 min y el teórico era 40 min, factor = 1.125 [un 12.5% más lento])
                $suma_factores += ($minutos_reales / $minutos_teoricos);
                $total_cargas_validas++;
            }
        }

        if ($total_cargas_validas > 0) {
            // Promedio del factor de rendimiento de tu Chromebook
            $factor_correccion = $suma_factores / $total_cargas_validas;
        }
    }
} catch (PDOException $e) {
    // Si falla la consulta, el factor se queda en 1.0 de forma segura (usa el estándar)
}

// 1. VERIFICAR SI HAY UNA CARGA EN CURSO (Último registro sin porcentaje final)
$carga_en_curso = null;
try {
    $sql_check = "SELECT id, fecha_registro, porcentaje_actual, minutos_carga, porcentaje_final_real 
                  FROM registros_bateria 
                  ORDER BY id DESC LIMIT 1";
    $stmt_check = $pdo->query($sql_check);
    $ultimo_registro = $stmt_check->fetch();

    if ($ultimo_registro && array_key_exists('porcentaje_final_real', $ultimo_registro) && $ultimo_registro['porcentaje_final_real'] === null) {
        $carga_en_curso = $ultimo_registro;
    }
} catch (PDOException $e) {
    $mensaje = 'Error al verificar estado de carga: ' . $e->getMessage();
    $tipo_mensaje = 'error';
}

// 2. PROCESAR LOS FORMULARIOS (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CASO A: Finalizar la carga en curso
    if (isset($_POST['accion']) && $_POST['accion'] === 'finalizar_carga' && $carga_en_curso) {
        $porcentaje_final_real = filter_input(INPUT_POST, 'porcentaje_final_real', FILTER_VALIDATE_INT);

        if ($porcentaje_final_real === false || $porcentaje_final_real < 0 || $porcentaje_final_real > 100) {
            $mensaje = 'Por favor, ingresa un porcentaje final válido entre 0 y 100.';
            $tipo_mensaje = 'error';
        } else {
            try {
                $sql_update = "UPDATE registros_bateria SET porcentaje_final_real = :porcentaje_final_real WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([
                    ':porcentaje_final_real' => $porcentaje_final_real,
                    ':id' => $carga_en_curso['id']
                ]);

                $mensaje = '¡Carga finalizada con éxito! El algoritmo ha aprendido de este registro.';
                $tipo_mensaje = 'success';
                $carga_en_curso = null;
            } catch (PDOException $e) {
                $mensaje = 'Error al actualizar el porcentaje final: ' . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        }
    }
    // CASO B: Calcular e iniciar una nueva carga con ALGORITMO ADAPTATIVO SEGMENTADO
    else if (isset($_POST['accion']) && $_POST['accion'] === 'calcular_carga') {
        $porcentaje_actual = filter_input(INPUT_POST, 'porcentaje_actual', FILTER_VALIDATE_INT);

        if ($porcentaje_actual === false || $porcentaje_actual < 0 || $porcentaje_actual > 100) {
            $mensaje = 'Por favor, ingresa un porcentaje válido entre 0 y 100.';
            $tipo_mensaje = 'error';
        } else {
            try {
                // Evitar duplicados el mismo día
                $hoy_fecha = date('Y-m-d');
                $sql_duplicado = "SELECT id FROM registros_bateria WHERE DATE(fecha_registro) = :hoy AND porcentaje_actual = :porcentaje";
                $stmt_duplicado = $pdo->prepare($sql_duplicado);
                $stmt_duplicado->execute([':hoy' => $hoy_fecha, ':porcentaje' => $porcentaje_actual]);

                if ($stmt_duplicado->fetch()) {
                    $mensaje = "Ya registraste una carga hoy iniciando con el " . $porcentaje_actual . "%. Intenta con otro valor.";
                    $tipo_mensaje = 'error';
                } else {
                    $porcentaje_objetivo = 80;
                    $punto_inflexion = 65;

                    // Aplicamos el factor de corrección inteligente calculado desde el historial a los tramos físicos
                    $tasa_rapida = 1.1 * $factor_correccion;
                    $tasa_lenta = 1.5 * $factor_correccion;

                    $minutos_carga = 0;
                    $porcentaje_temp = $porcentaje_actual;

                    if ($porcentaje_temp < $porcentaje_objetivo) {
                        // 1. Tramo rápido adaptado
                        if ($porcentaje_temp < $punto_inflexion) {
                            $puntos_tramo_1 = min($punto_inflexion, $porcentaje_objetivo) - $porcentaje_temp;
                            $minutos_carga += $puntos_tramo_1 * $tasa_rapida;
                            $porcentaje_temp = $punto_inflexion;
                        }

                        // 2. Tramo lento adaptado
                        if ($porcentaje_temp < $porcentaje_objetivo) {
                            $puntos_tramo_2 = $porcentaje_objetivo - $porcentaje_temp;
                            $minutos_carga += $puntos_tramo_2 * $tasa_lenta;
                        }
                    }

                    $minutos_carga = (int)round($minutos_carga);
                    $porcentaje_faltante = max(0, $porcentaje_objetivo - $porcentaje_actual);

                    $sql = "INSERT INTO registros_bateria (fecha_registro, porcentaje_actual, porcentaje_faltante, minutos_carga, porcentaje_final_real) 
                            VALUES (:fecha_registro, :porcentaje_actual, :porcentaje_faltante, :minutos_carga, NULL)";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':fecha_registro' => $fecha_actual_hora_actual,
                        ':porcentaje_actual' => $porcentaje_actual,
                        ':porcentaje_faltante' => $porcentaje_faltante,
                        ':minutos_carga' => $minutos_carga
                    ]);

                    $resultado_calculo = [
                        'porcentaje_actual' => $porcentaje_actual,
                        'porcentaje_faltante' => $porcentaje_faltante,
                        'minutos_carga' => $minutos_carga
                    ];

                    $porcentaje_desviacion = round(($factor_correccion - 1) * 100);
                    $texto_desviacion = $porcentaje_desviacion >= 0 ? "+$porcentaje_desviacion%" : "$porcentaje_desviacion%";
                    $mensaje = "Nueva carga calculada con curva física adaptada y calibración de historial del $texto_desviacion.";
                    $tipo_mensaje = 'success';

                    $carga_en_curso = [
                        'id' => $pdo->lastInsertId(),
                        'fecha_registro' => $fecha_actual_hora_actual,
                        'porcentaje_actual' => $porcentaje_actual,
                        'minutos_carga' => $minutos_carga
                    ];
                }
            } catch (PDOException $e) {
                $mensaje = 'Error al procesar el registro: ' . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        }
    }
}

// 3. OBTENER EL HISTORIAL ACTUALIZADO
$registros = [];
try {
    $sql = "SELECT id, fecha_registro, porcentaje_actual, porcentaje_faltante, minutes_carga = minutos_carga, porcentaje_final_real 
            FROM registros_bateria 
            ORDER BY fecha_registro DESC";
    // Nota: Corregido bug menor en alias nativo sql del index original
    $sql = "SELECT id, fecha_registro, porcentaje_actual, porcentaje_faltante, minutos_carga, porcentaje_final_real 
            FROM registros_bateria 
            ORDER BY fecha_registro DESC";
    $stmt = $pdo->query($sql);
    $registros = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensaje = 'Error al obtener el historial: ' . $e->getMessage();
    $tipo_mensaje = 'error';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Batería - Chromebook</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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

        .table-container {
            overflow-x: auto;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }
    </style>
</head>

<body>
    <div class="container-main">
        <header class="mb-8 rounded-2xl bg-gradient-to-r from-slate-900 to-indigo-950 p-6 text-center shadow-md md:p-8">
            <div class="inline-flex items-center justify-center gap-2.5 mb-3">
                <span class="text-3xl animate-bounce" style="animation-duration: 2s;">⚡</span>
                <h1 class="bg-gradient-to-r from-white via-indigo-100 to-indigo-200 bg-clip-text text-2xl font-extrabold tracking-tight text-transparent sm:text-3xl">
                    Gestor de Batería Chromebook
                </h1>
            </div>
            <p class="mx-auto max-w-xl text-sm font-medium text-indigo-200/80 sm:text-base mb-6 leading-relaxed">
                Optimiza la vida útil de tu dispositivo calculando y manteniendo la carga en su nivel óptimo del 80%.
            </p>
            <div class="flex justify-center">
                <a href="dashboard.php" class="group inline-flex items-center gap-2 rounded-xl bg-purple-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-all duration-300 ease-in-out hover:bg-purple-500 hover:shadow-purple-500/20 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600">
                    <span>Ver Dashboard de Batería</span>
                    <svg class="h-4 w-4 transition-transform duration-300 ease-in-out group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            </div>
        </header>

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm mb-6">

            <?php if (!empty($mensaje)): ?>
                <?php
                $is_success = ($tipo_mensaje === 'success');
                $alert_classes = $is_success ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200';
                ?>
                <div class="mb-5 flex items-center gap-2 rounded-lg border p-4 text-sm font-medium <?php echo $alert_classes; ?>">
                    <span><?php echo htmlspecialchars($mensaje); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($carga_en_curso): ?>
                <?php
                // Cálculo de la hora exacta de finalización para el enchufe inteligente
                $segundos_totales_carga = $carga_en_curso['minutos_carga'] * 60;
                $timestamp_inicio = strtotime($carga_en_curso['fecha_registro']);
                $timestamp_fin = $timestamp_inicio + $segundos_totales_carga;
                $hora_finalizacion = date('H:i', $timestamp_fin);
                ?>

                <div class="mb-4 flex items-center justify-between border-b border-orange-100 pb-4">
                    <h2 class="text-lg font-bold text-gray-800">⏳ Carga en Progreso...</h2>
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-700 ring-1 ring-inset ring-orange-700/10 animate-pulse">
                        Cargando desde el <?php echo $carga_en_curso['porcentaje_actual']; ?>%
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 text-sm text-gray-700">
                        <p class="text-xs font-bold uppercase text-indigo-500 tracking-wider mb-1">⏱️ Planificación Enchufe</p>
                        <p class="mb-1"><strong>Inicio:</strong> <?php echo date('H:i', $timestamp_inicio); ?></p>
                        <p class="text-base"><strong>⏰ Apagar enchufe a las:</strong> <span class="font-extrabold text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded"><?php echo $hora_finalizacion; ?></span></p>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-col justify-center items-center">
                        <span class="text-xs font-bold uppercase text-amber-600 tracking-wider mb-1">⏳ Tiempo Restante Real</span>
                        <div id="countdown-timer" class="text-2xl font-black text-amber-700 font-mono tracking-widest">
                            Calculando...
                        </div>
                    </div>
                </div>

                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="accion" value="finalizar_carga">
                    <div class="flex flex-col gap-1.5">
                        <label for="porcentaje_final_real" class="text-sm font-medium text-gray-700">
                            ¿Con qué porcentaje real retiraste la Chromebook? (%)
                        </label>
                        <div class="relative rounded-md shadow-sm">
                            <input
                                type="number"
                                id="porcentaje_final_real"
                                name="porcentaje_final_real"
                                class="w-full rounded-lg border border-orange-300 bg-white px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500/20 sm:text-sm transition-all"
                                min="0" max="100" step="1" placeholder="Ej: 80" required>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4">
                                <span class="text-gray-400 sm:text-sm">%</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full rounded-lg bg-orange-600 px-4 py-2.5 text-center text-sm font-bold text-white shadow-sm hover:bg-orange-500 transition-colors duration-150">
                        💾 Guardar Porcentaje Real y Cerrar Ciclo
                    </button>
                </form>

                <script>
                    const timestampFin = <?php echo $timestamp_fin; ?> * 1000;

                    function actualizarTemporizador() {
                        const ahora = new Date().getTime();
                        const diferencia = timestampFin - ahora;

                        if (diferencia <= 0) {
                            document.getElementById('countdown-timer').innerHTML = "¡LISTO! (Desconectar)";
                            document.getElementById('countdown-timer').classList.remove('text-amber-700');
                            document.getElementById('countdown-timer').classList.add('text-green-600', 'animate-bounce');
                            return;
                        }

                        const horas = floor(diferencia / (1000 * 60 * 60));
                        const minutos = floor((diferencia % (1000 * 60 * 60)) / (1000 * 60));
                        const segundos = floor((diferencia % (1000 * 60)) / 1000);

                        let texto = "";
                        if (horas > 0) texto += horas + "h ";
                        texto += minutos + "m " + segundos + "s";

                        document.getElementById('countdown-timer').innerHTML = texto;
                    }

                    function floor(valor) {
                        return Math.floor(valor);
                    }

                    actualizarTemporizador();
                    setInterval(actualizarTemporizador, 1000);
                </script>

            <?php else: ?>
                <div class="mb-6 flex items-center justify-between border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-bold text-gray-800">🔌 Calculadora de Carga</h2>
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                        🎯 Ritmo IA Actual: <?php echo round($factor_correccion, 2); ?> min/%
                    </div>
                </div>

                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="accion" value="calcular_carga">
                    <div class="flex flex-col gap-1.5">
                        <label for="porcentaje_actual" class="text-sm font-medium text-gray-700">
                            Ingresa el Porcentaje Actual de Batería (%)
                        </label>
                        <div class="relative rounded-md shadow-sm">
                            <input
                                type="number" id="porcentaje_actual" name="porcentaje_actual"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 sm:text-sm transition-all"
                                min="0" max="100" step="1" placeholder="Ej: 45" required>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4">
                                <span class="text-gray-400 sm:text-sm">%</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors duration-150">
                        Calcular Tiempo de Carga
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50/50 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span>📊</span> Historial de Registros
                </h2>
                <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full">
                    <?php echo count($registros ?? []); ?> registros
                </span>
            </div>

            <?php if (!empty($registros)): ?>
                <div class="table-container relative overflow-x-auto max-h-85 rounded-xl border border-gray-100 custom-scrollbar">
                    <table class="w-full border-collapse text-left text-sm text-gray-500">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600 sticky top-0 z-10 shadow-[0_1px_0_0_rgba(243,244,246,1)]">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-medium bg-gray-50">Fecha y Hora</th>
                                <th scope="col" class="px-6 py-4 font-medium text-center bg-gray-50">Porcentaje Inicial</th>
                                <th scope="col" class="px-6 py-4 font-medium text-center bg-gray-50">Minutos Carga</th>
                                <th scope="col" class="px-6 py-4 font-medium text-right bg-gray-50">Porcentaje Final Real</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php foreach ($registros as $registro): ?>
                                <tr class="hover:bg-gray-50/75 transition-colors duration-150">
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                        <?php echo date('d/m/Y H:i', strtotime($registro['fecha_registro'])); ?>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-center">
                                        <span class="font-semibold text-gray-700"><?php echo $registro['porcentaje_actual']; ?>%</span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-center font-semibold text-gray-900">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                                            <?php echo formatMinutesToHoursAndMinutes($registro['minutos_carga']); ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <?php if ($registro['porcentaje_final_real'] !== null): ?>
                                            <span class="inline-flex items-center rounded-md bg-green-50 px-2.5 py-1 text-xs font-bold text-green-700 ring-1 ring-inset ring-green-600/20">
                                                🎯 <?php echo $registro['porcentaje_final_real']; ?>% Real
                                            </span>
                                        <?php else: ?>
                                            <?php
                                            // Comprobamos si este registro es el primero de la lista (el más nuevo)
                                            // usando el ID de la carga en curso actual si existe
                                            $es_el_ultimo_activo = ($carga_en_curso && $registro['id'] == $carga_en_curso['id']);

                                            if ($es_el_ultimo_activo): ?>
                                                <span class="inline-flex items-center rounded-md bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20 animate-pulse">
                                                    ⚡ Cargando...
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-400/20">
                                                    💤 Sin datos
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-8 text-center text-gray-400">
                    <p class="text-base font-medium text-gray-600 mb-1">No hay registros aún</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>