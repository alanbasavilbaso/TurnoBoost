<?php

namespace App\Entity;

enum AppointmentSourceEnum: string
{
    case ADMIN = 'admin';           // Creada desde la agenda por el administrador
    case USER = 'user';             // Creada por el usuario desde /{domain}
    case API = 'api';               // Creada via API
    case IMPORT = 'import';         // Importada desde otro sistema
    case SYSTEM = 'system';         // Creada automáticamente por el sistema

    public function getLabel(): string
    {
        return match($this) {
            self::ADMIN => 'Administrador',
            self::USER => 'Usuario',
            self::API => 'API',
            self::IMPORT => 'Importación',
            self::SYSTEM => 'Sistema',
        };
    }

    public function isUserCreated(): bool
    {
        return $this === self::USER;
    }

    public function isAdminCreated(): bool
    {
        return $this === self::ADMIN;
    }
}