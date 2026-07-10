<?php
// Evitar acceso directo
if (count(get_included_files()) == 1) {
    http_response_code(403);
    exit("Acceso denegado.");
}

/**
 * Retorna la fase del macrociclo para una semana dada.
 */
function get_fase_semana($week, $total_weeks) {
    if ($week == $total_weeks) {
        return 'Carrera';
    }
    if ($week == $total_weeks - 1) {
        return 'Taper';
    }
    
    $remaining = $total_weeks - 2;
    if ($remaining <= 0) {
        return 'Base Aeróbica';
    }
    
    // Distribución proporcional
    $base_weeks = max(1, floor($remaining * 0.4));
    $fuerza_weeks = max(1, floor($remaining * 0.4));
    
    if ($week <= $base_weeks) {
        return 'Base Aeróbica';
    }
    if ($week <= ($base_weeks + $fuerza_weeks)) {
        return 'Fuerza y Cuestas';
    }
    return 'Específico';
}

/**
 * Calcula el volumen semanal recomendado en km.
 */
function get_volumen_semanal($week, $total_weeks, $nivel) {
    $peaks = [
        'Principiante' => 25,
        'Intermedio' => 45,
        'Avanzado' => 70,
        'Elite' => 100
    ];
    $peak_vol = isset($peaks[$nivel]) ? $peaks[$nivel] : 40;
    
    if ($week == $total_weeks) {
        return round($peak_vol * 0.4); // Semana de carrera
    }
    if ($week == $total_weeks - 1) {
        return round($peak_vol * 0.6); // Taper
    }
    
    // Ciclos de 3 semanas (2 carga, 1 descarga)
    $cycle_pos = ($week - 1) % 3; // 0, 1: Carga, 2: Descarga
    
    // Progresión del volumen base
    $progress = ($week - 1) / ($total_weeks - 2);
    $base_vol = $peak_vol * (0.6 + ($progress * 0.4));
    
    if ($cycle_pos == 2) {
        return round($base_vol * 0.7); // Descarga (30% menos)
    }
    return round($base_vol);
}

/**
 * Retorna la estructura de entrenamientos para la semana.
 */
function generar_estructura_semana($week, $total_weeks, $dias_semana, $nivel, $fase, $volumen) {
    $sesiones = [];
    
    // Definir proporciones de volumen por sesión
    // El Fondo suele ser el 40-50% del volumen
    // Calidad es 20-30%
    // Suaves/Regenerativos son el resto.
    
    if ($dias_semana == 3) {
        // Estructura 3 días: Calidad, Suave/Bici, Fondo
        $sesiones[] = [
            'dia' => 2, // Martes
            'tipo' => obtener_tipo_calidad($fase),
            'vol_pct' => 0.25,
            'nombre' => 'Sesión de Calidad'
        ];
        $sesiones[] = [
            'dia' => 4, // Jueves
            'tipo' => ($fase === 'Taper' || $fase === 'Carrera') ? 'Fondo' : 'Bici',
            'vol_pct' => 0.25,
            'nombre' => ($fase === 'Taper' || $fase === 'Carrera') ? 'Rodaje Suave' : 'Ciclismo Regenerativo'
        ];
        $sesiones[] = [
            'dia' => 6, // Sábado
            'tipo' => 'Fondo',
            'vol_pct' => 0.50,
            'nombre' => 'Fondo Largo'
        ];
    } elseif ($dias_semana == 4) {
        // Estructura 4 días: Calidad, Suave, Bici/Fondo Corto, Fondo
        $sesiones[] = [
            'dia' => 2, // Martes
            'tipo' => obtener_tipo_calidad($fase),
            'vol_pct' => 0.25,
            'nombre' => 'Sesión de Calidad'
        ];
        $sesiones[] = [
            'dia' => 3, // Miércoles
            'tipo' => 'Fondo',
            'vol_pct' => 0.20,
            'nombre' => 'Rodaje de Recuperación'
        ];
        $sesiones[] = [
            'dia' => 5, // Viernes
            'tipo' => ($fase === 'Base Aeróbica') ? 'Fondo' : 'Bici',
            'vol_pct' => 0.15,
            'nombre' => ($fase === 'Base Aeróbica') ? 'Rodaje Corto' : 'Ciclismo de Base'
        ];
        $sesiones[] = [
            'dia' => 7, // Domingo
            'tipo' => 'Fondo',
            'vol_pct' => 0.40,
            'nombre' => 'Fondo Largo'
        ];
    } else { // 5 días
        // Estructura 5 días: Calidad 1, Suave, Calidad 2/Bici, Suave, Fondo
        $sesiones[] = [
            'dia' => 2, // Martes
            'tipo' => obtener_tipo_calidad($fase),
            'vol_pct' => 0.20,
            'nombre' => 'Sesión de Calidad'
        ];
        $sesiones[] = [
            'dia' => 3, // Miércoles
            'tipo' => 'Fondo',
            'vol_pct' => 0.15,
            'nombre' => 'Rodaje Suave'
        ];
        $sesiones[] = [
            'dia' => 4, // Jueves
            'tipo' => ($fase === 'Fuerza y Cuestas') ? 'Cuestas' : 'Bici',
            'vol_pct' => 0.15,
            'nombre' => ($fase === 'Fuerza y Cuestas') ? 'Trabajo de Fuerza' : 'Ciclismo de Base'
        ];
        $sesiones[] = [
            'dia' => 5, // Viernes
            'tipo' => 'Fondo',
            'vol_pct' => 0.15,
            'nombre' => 'Rodaje Regenerativo'
        ];
        $sesiones[] = [
            'dia' => 7, // Domingo
            'tipo' => 'Fondo',
            'vol_pct' => 0.35,
            'nombre' => 'Fondo Largo'
        ];
    }
    
    // Ajustar km a cada sesión
    $resultado = [];
    foreach ($sesiones as $s) {
        $km = round($volumen * $s['vol_pct'], 1);
        if ($s['tipo'] === 'Bici') {
            $km = $km * 2.5; // Conversión de volumen a ciclismo
        }
        
        $terreno = 'Plano';
        if ($s['tipo'] === 'Fondo' && ($fase === 'Fuerza y Cuestas' || $fase === 'Específico')) {
            $terreno = 'Montaña';
        } elseif ($s['tipo'] === 'Cuestas') {
            $terreno = 'Técnico';
        }
        
        $resultado[] = [
            'dia' => $s['dia'],
            'tipo_sesion' => $s['tipo'],
            'titulo' => $s['nombre'],
            'distancia_km' => $km,
            'terreno' => $terreno,
            'ritmo_sugerido' => obtener_ritmo_sugerido($s['tipo'], $nivel),
            'descripcion' => '' // Se rellena por Plantilla (Modo A) o Gemini (Modo C)
        ];
    }
    
    return $resultado;
}

/**
 * Helper para obtener el tipo de sesión de calidad según la fase.
 */
function obtener_tipo_calidad($fase) {
    switch ($fase) {
        case 'Base Aeróbica':
            return 'Cambios de Ritmo';
        case 'Fuerza y Cuestas':
            return 'Cuestas';
        case 'Específico':
            return 'Pasadas';
        case 'Taper':
            return 'Pasadas'; // Taper suave con intensidad pero poco volumen
        default:
            return 'Fondo';
    }
}

/**
 * Helper para ritmo de referencia.
 */
function obtener_ritmo_sugerido($tipo, $nivel) {
    if ($tipo === 'Bici') {
        return 'Z2 Aeróbico (Bici)';
    }
    
    $ritmos = [
        'Principiante' => [
            'Fondo' => '6:30 - 7:30 min/km',
            'Pasadas' => '5:30 min/km',
            'Cuestas' => 'Esfuerzo Alto (Cuesta)',
            'Cambios de Ritmo' => 'Ritmo Variable'
        ],
        'Intermedio' => [
            'Fondo' => '5:30 - 6:15 min/km',
            'Pasadas' => '4:30 min/km',
            'Cuestas' => 'Esfuerzo Z4 (Cuesta)',
            'Cambios de Ritmo' => 'Alternar Z2 y Z4'
        ],
        'Avanzado' => [
            'Fondo' => '4:45 - 5:15 min/km',
            'Pasadas' => '3:50 min/km',
            'Cuestas' => 'Esfuerzo Z5 (Cuesta)',
            'Cambios de Ritmo' => 'Z3 Progresivo'
        ],
        'Elite' => [
            'Fondo' => '4:00 - 4:30 min/km',
            'Pasadas' => '3:15 min/km',
            'Cuestas' => 'Z5 Máximo (Cuesta)',
            'Cambios de Ritmo' => 'Z4 Sostenido'
        ]
    ];
    
    return isset($ritmos[$nivel][$tipo]) ? $ritmos[$nivel][$tipo] : 'Ritmo controlado';
}
