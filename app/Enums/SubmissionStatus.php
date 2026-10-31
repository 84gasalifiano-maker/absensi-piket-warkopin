<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Kedaluwarsa = 'kedaluwarsa';
}
