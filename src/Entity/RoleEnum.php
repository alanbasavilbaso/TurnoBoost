<?php

namespace App\Entity;

enum RoleEnum: string
{
    case ADMIN = 'admin';
    case PROFESIONAL = 'profesional';
    case RECEPCIONISTA = 'recepcionista';
    case PACIENTE = 'paciente';
}