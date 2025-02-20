<?php

namespace App\StateMachines;

use App\Enums\DownloadStatus;
use Asantibanez\LaravelEloquentStateMachines\StateMachines\StateMachine;

class DownloadStatusStateMachine extends StateMachine
{
    public function recordHistory(): bool
    {
        return true;
    }

    public function transitions(): array
    {
        return [
            '*' => [DownloadStatus::PENDING(), DownloadStatus::CANCELLED(), DownloadStatus::DOWNLOAD_CLOUD(), DownloadStatus::DOWNLOAD_PENDING()],
            DownloadStatus::PENDING() => [DownloadStatus::DOWNLOAD_CLOUD()],
            DownloadStatus::DOWNLOAD_CLOUD() => [DownloadStatus::DOWNLOAD_PENDING(), DownloadStatus::PENDING()],
            DownloadStatus::DOWNLOAD_PENDING() => [DownloadStatus::DOWNLOAD_LOCAL(), DownloadStatus::DOWNLOAD_CLOUD()],
            DownloadStatus::DOWNLOAD_LOCAL() => [DownloadStatus::PROCESSING(), DownloadStatus::DOWNLOAD_PENDING()],
            DownloadStatus::PROCESSING() => [DownloadStatus::COMPLETED(), DownloadStatus::DOWNLOAD_LOCAL()],
        ];
    }

    public function defaultState(): ?string
    {
        return null;
    }
}
