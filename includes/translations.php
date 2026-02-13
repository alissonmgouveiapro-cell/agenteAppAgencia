<?php
/* Arquivo: includes/translations.php */
/* Central de Idiomas COMPLETA */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lang = $_SESSION['user_lang'] ?? 'pt_BR';

$full_dict = [
    'pt_BR' => [
        // Menu
        'dashboard' => 'Dashboard',
        'notifs' => 'Notificações',
        'projects' => 'Projetos',
        'clients' => 'Clientes',
        'team' => 'Equipe',
        'radar' => 'Radar Alocação',
        'meetings' => 'Reuniões',
        'account' => 'Minha Conta',
        'prefs' => 'Preferências',
        'theme' => 'Tema',
        'logout' => 'Sair',
        
        // DASHBOARD (As chaves que faltavam)
        'active_projects' => 'Projetos Ativos',
        'total_clients' => 'Total de Clientes',
        'pending_tasks' => 'Tarefas Pendentes',
        'recent_activity' => 'Atividade Recente',
        'no_activity' => 'Nenhuma atividade recente.',
        'view' => 'Visualizar',
        'st_done' => 'Concluído',
        'st_progress' => 'Em andamento',
        'st_pending' => 'Pendente',
        'proj_deadline' => 'Prazo',
        'status' => 'Status',
        'actions' => 'Ações',
        
        // Outros
        'welcome' => 'Bem-vindo',
        'save' => 'Salvar',
        'cancel' => 'Cancelar',
        
        // Preferências
        'title' => 'Preferências',
        'subtitle' => 'Personalize sua experiência.',
        'msg_success' => 'Salvo com sucesso!',
        'sec_general' => 'Geral',
        'lbl_lang' => 'Idioma',
        'desc_lang' => 'Idioma do sistema',
        'lbl_font' => 'Fonte',
        'desc_font' => 'Estilo da letra',
        'lbl_tz' => 'Fuso Horário',
        'desc_tz' => 'Hora local',
        'opt_auto' => 'Automático',
        'opt_manual' => 'Manual',
        'txt_detected' => 'Detectado',
        'btn_save' => 'Salvar',
        'font_def' => 'Padrão',
        'font_mod' => 'Moderna',
        'font_serif' => 'Clássica',
        'font_mono' => 'Técnica',
        'preview_txt' => 'Texto de exemplo 123'
    ],
    
    'en_US' => [
        'dashboard' => 'Dashboard',
        'notifs' => 'Notifications',
        'projects' => 'Projects',
        'clients' => 'Clients',
        'team' => 'Team',
        'radar' => 'Radar View',
        'meetings' => 'Meetings',
        'account' => 'My Profile',
        'prefs' => 'Settings',
        'theme' => 'Theme',
        'logout' => 'Logout',
        'active_projects' => 'Active Projects',
        'total_clients' => 'Total Clients',
        'pending_tasks' => 'Pending Tasks',
        'recent_activity' => 'Recent Activity',
        'no_activity' => 'No recent activity.',
        'view' => 'View',
        'st_done' => 'Done',
        'st_progress' => 'In Progress',
        'st_pending' => 'Pending',
        'proj_deadline' => 'Deadline',
        'status' => 'Status',
        'actions' => 'Actions',
        'welcome' => 'Welcome',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'title' => 'Settings',
        'subtitle' => 'Customize experience.',
        'msg_success' => 'Saved successfully!',
        'sec_general' => 'General',
        'lbl_lang' => 'Language',
        'desc_lang' => 'System language',
        'lbl_font' => 'Font',
        'desc_font' => 'Font style',
        'lbl_tz' => 'Timezone',
        'desc_tz' => 'Local time',
        'opt_auto' => 'Auto',
        'opt_manual' => 'Manual',
        'txt_detected' => 'Detected',
        'btn_save' => 'Save',
        'font_def' => 'Default',
        'font_mod' => 'Modern',
        'font_serif' => 'Classic',
        'font_mono' => 'Mono',
        'preview_txt' => 'Sample text 123'
    ],
    
    'es_ES' => [
        'dashboard' => 'Tablero',
        'notifs' => 'Notificaciones',
        'projects' => 'Proyectos',
        'clients' => 'Clientes',
        'team' => 'Equipo',
        'radar' => 'Radar',
        'meetings' => 'Reuniones',
        'account' => 'Mi Cuenta',
        'prefs' => 'Preferencias',
        'theme' => 'Tema',
        'logout' => 'Salir',
        'active_projects' => 'Proyectos Activos',
        'total_clients' => 'Total Clientes',
        'pending_tasks' => 'Tareas Pendientes',
        'recent_activity' => 'Actividad Reciente',
        'no_activity' => 'Sin actividad.',
        'view' => 'Ver',
        'st_done' => 'Hecho',
        'st_progress' => 'En Progreso',
        'st_pending' => 'Pendiente',
        'proj_deadline' => 'Plazo',
        'status' => 'Estado',
        'actions' => 'Acciones',
        'welcome' => 'Bienvenido',
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'title' => 'Preferencias',
        'subtitle' => 'Personalizar.',
        'msg_success' => '¡Guardado!',
        'sec_general' => 'General',
        'lbl_lang' => 'Idioma',
        'desc_lang' => 'Idioma del sistema',
        'lbl_font' => 'Fuente',
        'desc_font' => 'Estilo de letra',
        'lbl_tz' => 'Zona Horaria',
        'desc_tz' => 'Hora local',
        'opt_auto' => 'Automático',
        'opt_manual' => 'Manual',
        'txt_detected' => 'Detectado',
        'btn_save' => 'Guardar',
        'font_def' => 'Estándar',
        'font_mod' => 'Moderna',
        'font_serif' => 'Clásica',
        'font_mono' => 'Mono',
        'preview_txt' => 'Texto de ejemplo 123'
    ]
];

// Fallback
$full_dict['pt_PT'] = $full_dict['pt_BR'];

// Seleção segura
if (!array_key_exists($lang, $full_dict)) {
    $lang = 'pt_BR';
}

// Variável global usada no site
$t = $full_dict[$lang];
?>