<?php

declare(strict_types=1);

namespace App\Imports;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ResidentsImport implements SkipsEmptyRows, WithHeadingRow
{
    //
}
