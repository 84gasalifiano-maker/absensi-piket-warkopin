<?php

namespace App\Enums;

enum Role: string
{
    case Siswa = 'siswa';
    case PjKebersihan = 'pj_kebersihan';
    case Guru = 'guru';
    case Admin = 'admin';
}
