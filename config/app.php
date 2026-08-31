<?php
$idleEnv=getenv('SESSION_IDLE_MINUTES');$absoluteEnv=getenv('SESSION_ABSOLUTE_MINUTES');
return [
    'name' => 'INPACTA',
    'timezone' => 'America/Sao_Paulo',
    'session_name' => getenv('SESSION_NAME') ?: 'INPACTA_SESSION',
    'session_secure' => filter_var(getenv('SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    // Use 0 para desativar uma das políticas.
    'session_idle_seconds' => max(0,(int)($idleEnv===false||$idleEnv===''?60:$idleEnv))*60,
    'session_absolute_seconds' => max(0,(int)($absoluteEnv===false||$absoluteEnv===''?480:$absoluteEnv))*60,
];
