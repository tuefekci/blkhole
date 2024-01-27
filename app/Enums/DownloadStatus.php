<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;
use ArchTech\Enums\Options;


enum DownloadStatus: int
{
    use InvokableCases;
	use Names;
	use Values;
	use Options;

    case PENDING = 0;
    case DOWNLOAD_CLOUD = 1;
    case DOWNLOAD_PENDING = 2;
    case DOWNLOAD_LOCAL = 3;
    case PROCESSING = 4;
    case COMPLETED = 5;
    case CANCELLED = 6;
}
