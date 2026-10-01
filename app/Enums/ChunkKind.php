<?php

namespace App\Enums;

enum ChunkKind: string
{
    case Text = 'text';
    case Table = 'table';
    case List = 'list';
}
