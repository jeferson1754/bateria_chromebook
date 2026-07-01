<?php
require 'conexion.php';

/**
 * Convierte un número de minutos a un formato de "Xh Ymin" o "Ymin".
 * @param int $totalMinutes El total de minutos a formatear.
 * @return string El tiempo formateado.
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


// Variables para almacenar mensajes
$mensaje = '';
$tipo_mensaje = '';
$resultado_calculo = null;

// Procesar el formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $porcentaje_actual = filter_input(INPUT_POST, 'porcentaje_actual', FILTER_VALIDATE_INT);

    // Validar que el porcentaje sea válido (entre 0 y 100)
    if ($porcentaje_actual === false || $porcentaje_actual < 0 || $porcentaje_actual > 100) {
        $mensaje = 'Por favor, ingresa un porcentaje válido entre 0 y 100.';
        $tipo_mensaje = 'error';
    } else {
        // Constantes de cálculo: 30 minutos = 20% (1.5 minutos por 1%)
        $porcentaje_objetivo = 80;
        $porcentaje_faltante = max(0, $porcentaje_objetivo - $porcentaje_actual);
        $minutos_por_porcentaje = 1.5; // 30 minutos / 20% = 1.5 minutos por %
        $minutos_carga = (int)round($porcentaje_faltante * $minutos_por_porcentaje);

        // Preparar e insertar el registro en la base de datos
        try {
            $sql = "INSERT INTO registros_bateria (porcentaje_actual, porcentaje_faltante, minutos_carga) 
                    VALUES (:porcentaje_actual, :porcentaje_faltante, :minutos_carga)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':porcentaje_actual' => $porcentaje_actual,
                ':porcentaje_faltante' => $porcentaje_faltante,
                ':minutos_carga' => $minutos_carga
            ]);

            $resultado_calculo = [
                'porcentaje_actual' => $porcentaje_actual,
                'porcentaje_faltante' => $porcentaje_faltante,
                'minutos_carga' => $minutos_carga
            ];

            $mensaje = 'Registro guardado exitosamente.';
            $tipo_mensaje = 'success';
        } catch (PDOException $e) {
            $mensaje = 'Error al guardar el registro: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

// Obtener el historial de registros ordenados del más reciente al más antiguo
$registros = [];
try {
    $sql = "SELECT id, fecha_registro, porcentaje_actual, porcentaje_faltante, minutos_carga 
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

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .target-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.1rem;
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 1rem;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .result-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .result-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 1.05rem;
        }

        .result-label {
            color: #555;
            font-weight: 600;
        }

        .result-value {
            color: #667eea;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }

        th {
            padding: 15px;
            text-align: left;
            color: #333;
            font-weight: 600;
            font-size: 0.95rem;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #555;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            margin-top: 30px;
        }

        .no-records {
            text-align: center;
            color: #999;
            padding: 30px;
            font-size: 1.05rem;
        }
    </style>
</head>

<body>
    <div class="container-main">
        <!-- Header -->
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

        <!-- Card Principal -->
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm mb-2">

            <?php if (!empty($mensaje)): ?>
                <?php
                // Identificamos el tipo de alerta para aplicar los colores correctos de Tailwind
                $is_success = ($tipo_mensaje === 'success');
                $alert_classes = $is_success
                    ? 'bg-green-50 text-green-800 border-green-200'
                    : 'bg-red-50 text-red-800 border-red-200';
                ?>
                <div class="mb-5 flex items-center gap-2 rounded-lg border p-4 text-sm font-medium <?php echo $alert_classes; ?>">
                    <?php if ($is_success): ?>
                        <svg class="h-5 w-5 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    <?php else: ?>
                        <svg class="h-5 w-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($mensaje); ?></span>
                </div>
            <?php endif; ?>

            <div class="mb-6 flex items-center justify-between border-b border-gray-100 pb-4">
                <h2 class="text-lg font-bold text-gray-800">🔌 Calculadora de Carga</h2>
                <div class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                    </span>
                    🎯 Meta Óptima: 80%
                </div>
            </div>

            <form method="POST" action="" class="space-y-4">
                <div class="flex flex-col gap-1.5">
                    <label for="porcentaje_actual" class="text-sm font-medium text-gray-700">
                        Ingresa el Porcentaje Actual de Batería (%)
                    </label>
                    <div class="relative rounded-md shadow-sm">
                        <input
                            type="number"
                            id="porcentaje_actual"
                            name="porcentaje_actual"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 sm:text-sm transition-all"
                            min="0"
                            max="100"
                            step="1"
                            placeholder="Ej: 45"
                            required>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4">
                            <span class="text-gray-400 sm:text-sm">%</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors duration-150">
                    Calcular Tiempo de Carga
                </button>
            </form>

            <?php if ($resultado_calculo): ?>
                <div class="mt-6 rounded-xl border border-indigo-100 bg-indigo-50/30 p-5">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-800 mb-3">⚡ Tiempo de Carga Sugerido</h3>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between items-center border-b border-indigo-100/50 pb-2">
                            <span class="text-gray-600">Porcentaje Actual:</span>
                            <span class="font-semibold text-gray-900"><?php echo $resultado_calculo['porcentaje_actual']; ?>%</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-indigo-100/50 pb-2">
                            <span class="text-gray-600">Porcentaje Faltante (hasta 80%):</span>
                            <span class="font-semibold text-orange-600"><?php echo $resultado_calculo['porcentaje_faltante']; ?>%</span>
                        </div>
                        <div class="flex justify-between items-center pt-1">
                            <span class="font-medium text-gray-800">Tiempo estimado en el cargador:</span>
                            <span class="text-base font-bold text-indigo-700 bg-indigo-100/80 px-2.5 py-0.5 rounded-md">
                                <?php echo formatMinutesToHoursAndMinutes($resultado_calculo['minutos_carga']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Dashboard de Historial -->
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50/50 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span>📊</span> Historial de Registros
                </h2>
                <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full">
                    <?php echo count($registros ?? []); ?> registros en total
                </span>
            </div>

            <?php if (!empty($registros)): ?>
                <div class="table-container relative overflow-x-auto overflow-y-auto max-h-85 rounded-xl border border-gray-100 shadow-sm custom-scrollbar">
                    <table class="w-full border-collapse text-left text-sm text-gray-500">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600 sticky top-0 z-10 shadow-[0_1px_0_0_rgba(243,244,246,1)]">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-medium bg-gray-50">Fecha y Hora</th>
                                <th scope="col" class="px-6 py-4 font-medium text-center bg-gray-50">Porcentaje Actual</th>
                                <th scope="col" class="px-6 py-4 font-medium text-center bg-gray-50">Porcentaje Faltante</th>
                                <th scope="col" class="px-6 py-4 font-medium text-right bg-gray-50">Minutos de Carga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php foreach ($registros as $registro): ?>
                                <tr class="hover:bg-gray-50/75 transition-colors duration-150">
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                        <?php echo date('d/m/Y H:i', strtotime($registro['fecha_registro'])); ?>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <span class="font-semibold text-gray-700"><?php echo $registro['porcentaje_actual']; ?>%</span>
                                            <div class="w-12 bg-gray-200 rounded-full h-1.5 hidden sm:block">
                                                <div class="bg-indigo-500 h-1.5 rounded-full" style="width: <?php echo min(100, max(0, $registro['porcentaje_actual'])); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4 text-center">
                                        <span class="inline-flex items-center rounded-md bg-orange-50 px-2 py-1 text-xs font-medium text-orange-700 ring-1 ring-inset ring-orange-600/10">
                                            <?php echo $registro['porcentaje_faltante']; ?>% restante
                                        </span>
                </div>

                <td class="whitespace-nowrap px-6 py-4 text-right font-semibold text-gray-900">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                        <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <?php
                                if (function_exists('formatMinutesToHoursAndMinutes')) {
                                    echo formatMinutesToHoursAndMinutes($registro['minutos_carga']);
                                } else {
                                    echo $registro['minutos_carga'] . ' min';
                                }
                        ?>
                    </span>
                </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        </div>

        <style>
            .custom-scrollbar::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }

            .custom-scrollbar::-webkit-scrollbar-track {
                background: transparent;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: #e2e8f0;
                /* Color gris suave (slate-200) */
                border-radius: 8px;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #cbd5e1;
                /* Gris un poco más oscuro al pasar el cursor */
            }
        </style>
    <?php else: ?>
        <div class="p-8 text-center text-gray-400">
            <div class="text-4xl mb-3">🔋</div>
            <p class="text-base font-medium text-gray-600 mb-1">No hay registros aún</p>
            <p class="text-xs text-gray-400">¡Ingresa el porcentaje de batería actual para comenzar a calcular y trackear!</p>
        </div>
    <?php endif; ?>
    </div>
    </div>
</body>

</html>